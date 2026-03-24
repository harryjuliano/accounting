<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BalanceSheetReportController extends Controller
{
    use InteractsWithCompanyScope;

    public function __invoke(Request $request)
    {
        $timezone = $request->user()?->company?->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);

        $yearOptions = JournalEntry::query()
            ->selectRaw('DISTINCT YEAR(posting_date) as year')
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
            ->whereNotNull('posting_date')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value) => (int) $value)
            ->values();

        $requestedYear = (int) $request->integer('year');
        $year = $requestedYear > 0
            ? $requestedYear
            : ($yearOptions->first() ?: $now->year);

        if (! $yearOptions->contains($year)) {
            $yearOptions = $yearOptions->prepend($year)->unique()->sortDesc()->values();
        }

        $defaultPeriod = $year === $now->year ? $now->month : 12;
        $period = (int) ($request->input('period') ?: $defaultPeriod);
        $period = max(1, min(12, $period));

        $companyId = $request->input('company_id', 'all');
        $branchId = $request->input('branch_id', 'all');
        $drillLevel = max(1, min(4, (int) $request->integer('drill_level', 1)));

        $status = strtolower($request->string('status')->toString() ?: 'posted');
        $allowedStatuses = ['all', 'draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'posted';
        }

        $currentYearStart = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $currentPeriodEnd = Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->endOfMonth();

        $previousYear = $year - 1;
        $previousYearStart = Carbon::create($previousYear, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $previousPeriodEnd = Carbon::create($previousYear, $period, 1, 0, 0, 0, $timezone)->endOfMonth();

        $scope = fn (Builder $query) => $query
            ->when($companyId !== 'all', fn (Builder $q) => $q->where('journal_entries.company_id', $companyId))
            ->when($branchId !== 'all', fn (Builder $q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($status !== 'all', fn (Builder $q) => $q->where('journal_entries.status', $status));

        $buildBalanceMap = fn (Carbon $from, Carbon $to) => JournalLine::query()
            ->selectRaw('journal_lines.account_id')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('journal_entries.company_id', $request->user()->company_id))
            ->whereDate('journal_entries.posting_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $to->toDateString())
            ->whereNotIn('journal_entries.journal_type', ['closing'])
            ->tap($scope)
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        // Neraca menggunakan carry-forward: nilai periode adalah saldo kumulatif dari awal tahun.
        $currentMap = $buildBalanceMap($currentYearStart, $currentPeriodEnd);
        $previousMap = $buildBalanceMap($previousYearStart, $previousPeriodEnd);

        $accounts = ChartOfAccount::query()
            ->select('chart_of_accounts.id', 'chart_of_accounts.company_id', 'chart_of_accounts.parent_id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.level', 'chart_of_accounts.normal_balance', 'chart_of_accounts.is_active', 'account_groups.type as account_group_type')
            ->leftJoin('account_groups', 'account_groups.id', '=', 'chart_of_accounts.account_group_id')
            ->where('chart_of_accounts.is_active', true)
            ->where('chart_of_accounts.level', 4)
            ->whereIn('account_groups.type', ['asset', 'liability', 'equity'])
            ->with([
                'parent:id,parent_id,code,name,level',
                'parent.parent:id,parent_id,code,name,level',
                'parent.parent.parent:id,parent_id,code,name,level',
            ])
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('chart_of_accounts.company_id', $request->user()->company_id))
            ->when($companyId !== 'all', fn (Builder $query) => $query->where('chart_of_accounts.company_id', $companyId))
            ->orderBy('chart_of_accounts.code')
            ->get();

        $segmentName = [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'current_year_profit' => 'Current Year Profit',
        ];

        $baseRows = $accounts->map(function (ChartOfAccount $account) use ($currentMap, $previousMap, $segmentName) {
            $currentDebit = (float) ($currentMap->get($account->id)?->debit ?? 0);
            $currentCredit = (float) ($currentMap->get($account->id)?->credit ?? 0);
            $previousDebit = (float) ($previousMap->get($account->id)?->debit ?? 0);
            $previousCredit = (float) ($previousMap->get($account->id)?->credit ?? 0);

            $normalBalance = $account->normal_balance === 'credit' ? 'credit' : 'debit';
            $currentAmount = $normalBalance === 'credit'
                ? $currentCredit - $currentDebit
                : $currentDebit - $currentCredit;
            $previousAmount = $normalBalance === 'credit'
                ? $previousCredit - $previousDebit
                : $previousDebit - $previousCredit;
            $variance = $currentAmount - $previousAmount;

            $level3 = $account->parent;
            $level2 = $level3?->parent;
            $level1 = $level2?->parent;

            return [
                'coa_id' => $account->id,
                'segment_key' => $account->account_group_type,
                'segment' => $segmentName[$account->account_group_type] ?? 'Other',
                'coa_level_1_id' => $level1?->id,
                'coa_level_2_id' => $level2?->id,
                'coa_level_3_id' => $level3?->id,
                'coa_level_4_id' => $account->id,
                'coa_level_1' => $level1?->name,
                'coa_level_2' => $level2?->name,
                'coa_level_3' => $level3?->name,
                'coa_level_4' => $account->name,
                'coa_code' => $account->code,
                'normal_balance' => $normalBalance,
                'current_year' => $currentAmount,
                'previous_year' => $previousAmount,
                'variance' => $variance,
            ];
        })->values();

        $buildProfit = function (Carbon $from, Carbon $to) use ($request, $scope) {
            $profitMap = JournalLine::query()
                ->selectRaw('journal_lines.account_id')
                ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
                ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
                ->join('account_groups', 'account_groups.id', '=', 'chart_of_accounts.account_group_id')
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('journal_entries.company_id', $request->user()->company_id))
                ->whereDate('journal_entries.posting_date', '>=', $from->toDateString())
                ->whereDate('journal_entries.posting_date', '<=', $to->toDateString())
                ->whereNotIn('journal_entries.journal_type', ['opening', 'closing'])
                ->whereIn('account_groups.type', ['revenue', 'cogs', 'expense', 'other_income', 'other_expense'])
                ->tap($scope)
                ->groupBy('journal_lines.account_id')
                ->get();

            $profitCurrent = 0.0;
            foreach ($profitMap as $item) {
                $debit = (float) $item->debit;
                $credit = (float) $item->credit;
                // Ringkasan tanpa normal balance per akun: revenue(+), expenses(-)
                $profitCurrent += $credit - $debit;
            }

            return $profitCurrent;
        };

        $currentYearProfit = $buildProfit($currentYearStart, $currentPeriodEnd);
        $previousYearProfit = $buildProfit($previousYearStart, $previousPeriodEnd);
        $profitVariance = $currentYearProfit - $previousYearProfit;

        $baseRows = $baseRows->push([
            'coa_id' => null,
            'segment_key' => 'current_year_profit',
            'segment' => $segmentName['current_year_profit'],
            'coa_level_1_id' => null,
            'coa_level_2_id' => null,
            'coa_level_3_id' => null,
            'coa_level_4_id' => null,
            'coa_level_1' => 'Current Year Profit',
            'coa_level_2' => null,
            'coa_level_3' => null,
            'coa_level_4' => null,
            'coa_code' => null,
            'normal_balance' => 'credit',
            'current_year' => $currentYearProfit,
            'previous_year' => $previousYearProfit,
            'variance' => $profitVariance,
        ]);

        $groupByKeys = [
            1 => ['segment_key', 'segment', 'coa_level_1_id', 'coa_level_1'],
            2 => ['segment_key', 'segment', 'coa_level_1_id', 'coa_level_1', 'coa_level_2_id', 'coa_level_2'],
            3 => ['segment_key', 'segment', 'coa_level_1_id', 'coa_level_1', 'coa_level_2_id', 'coa_level_2', 'coa_level_3_id', 'coa_level_3'],
            4 => ['segment_key', 'segment', 'coa_level_1_id', 'coa_level_1', 'coa_level_2_id', 'coa_level_2', 'coa_level_3_id', 'coa_level_3', 'coa_level_4_id', 'coa_level_4', 'coa_code'],
        ];

        $rows = $baseRows
            ->groupBy(function (array $row) use ($groupByKeys, $drillLevel) {
                return collect($groupByKeys[$drillLevel])->map(fn ($key) => (string) ($row[$key] ?? ''))->implode('|');
            })
            ->map(function ($items) use ($drillLevel) {
                $first = $items->first();

                $segmentOrderMap = ['asset' => 1, 'liability' => 2, 'equity' => 3, 'current_year_profit' => 4];

                return [
                    'segment_key' => $first['segment_key'] ?? null,
                    'segment_order' => $segmentOrderMap[$first['segment_key'] ?? ''] ?? 99,
                    'segment' => $first['segment'] ?? null,
                    'coa_level_1' => $first['coa_level_1'] ?? null,
                    'coa_level_2' => $drillLevel >= 2 ? ($first['coa_level_2'] ?? null) : null,
                    'coa_level_3' => $drillLevel >= 3 ? ($first['coa_level_3'] ?? null) : null,
                    'coa_level_4' => $drillLevel >= 4 ? ($first['coa_level_4'] ?? null) : null,
                    'coa_code' => $drillLevel >= 4 ? ($first['coa_code'] ?? null) : null,
                    'current_year' => (float) $items->sum('current_year'),
                    'previous_year' => (float) $items->sum('previous_year'),
                    'variance' => (float) $items->sum('variance'),
                ];
            })
            ->sortBy([
                ['segment_order', 'asc'],
                ['coa_code', 'asc'],
                ['coa_level_1', 'asc'],
            ])
            ->values();

        $totalAssetCurrent = (float) $baseRows->where('segment_key', 'asset')->sum('current_year');
        $totalAssetPrevious = (float) $baseRows->where('segment_key', 'asset')->sum('previous_year');
        $totalAssetVariance = $totalAssetCurrent - $totalAssetPrevious;

        $rows = $rows->map(function (array $row) use ($totalAssetCurrent, $totalAssetPrevious, $totalAssetVariance) {
            $currentPercent = abs($totalAssetCurrent) > 0.000001
                ? ($row['current_year'] / $totalAssetCurrent) * 100
                : 0;
            $previousPercent = abs($totalAssetPrevious) > 0.000001
                ? ($row['previous_year'] / $totalAssetPrevious) * 100
                : 0;
            $variancePercent = abs($totalAssetVariance) > 0.000001
                ? ($row['variance'] / $totalAssetVariance) * 100
                : 0;

            return [
                ...$row,
                'current_year_percent_asset' => $currentPercent,
                'previous_year_percent_asset' => $previousPercent,
                'variance_percent_asset' => $variancePercent,
            ];
        })->values();

        $summary = [
            'total_asset_current_year' => $totalAssetCurrent,
            'total_asset_previous_year' => $totalAssetPrevious,
            'total_asset_variance' => $totalAssetVariance,
            'total_liability_current_year' => (float) $baseRows->where('segment_key', 'liability')->sum('current_year'),
            'total_liability_previous_year' => (float) $baseRows->where('segment_key', 'liability')->sum('previous_year'),
            'total_equity_current_year' => (float) $baseRows->where('segment_key', 'equity')->sum('current_year'),
            'total_equity_previous_year' => (float) $baseRows->where('segment_key', 'equity')->sum('previous_year'),
            'current_year_profit_current_year' => $currentYearProfit,
            'current_year_profit_previous_year' => $previousYearProfit,
            'total_right_side_current_year' => (float) $baseRows->whereIn('segment_key', ['liability', 'equity', 'current_year_profit'])->sum('current_year'),
            'total_right_side_previous_year' => (float) $baseRows->whereIn('segment_key', ['liability', 'equity', 'current_year_profit'])->sum('previous_year'),
        ];

        return inertia('Apps/Reports/BalanceSheet/Index', [
            'rows' => $rows,
            'summary' => $summary,
            'yearOptions' => $yearOptions,
            'companies' => $this->getAccessibleCompanies(),
            'branches' => Branch::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'statusOptions' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'pending_approval', 'label' => 'Pending Approval'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'posted', 'label' => 'Posted'],
                ['value' => 'reversed', 'label' => 'Reversed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'filters' => [
                'type' => 'MTD',
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'status' => $status,
                'year' => $year,
                'period' => $period,
                'drill_level' => $drillLevel,
            ],
        ]);
    }
}
