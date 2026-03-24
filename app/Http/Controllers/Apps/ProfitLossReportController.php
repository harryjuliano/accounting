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

class ProfitLossReportController extends Controller
{
    use InteractsWithCompanyScope;

    public function __invoke(Request $request)
    {
        $report = $this->buildReport($request);
        $export = strtolower($request->string('export')->toString());

        if ($export === 'pdf') {
            return response()->view('reports.profit-loss.pdf', [
                ...$report,
                'generatedAt' => now()->format('d M Y H:i'),
            ]);
        }

        return inertia('Apps/Reports/ProfitLoss/Index', $report);
    }

    private function buildReport(Request $request): array
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

        $reportType = strtoupper($request->string('type')->toString() ?: 'MTD');
        if (! in_array($reportType, ['MTD', 'YTD'], true)) {
            $reportType = 'MTD';
        }

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
        $currentPeriodStart = Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->startOfDay();
        $currentPeriodEnd = $currentPeriodStart->copy()->endOfMonth();

        $previousYear = $year - 1;
        $previousYearStart = Carbon::create($previousYear, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $previousPeriodStart = Carbon::create($previousYear, $period, 1, 0, 0, 0, $timezone)->startOfDay();
        $previousPeriodEnd = $previousPeriodStart->copy()->endOfMonth();

        $currentStart = $reportType === 'YTD' ? $currentYearStart->copy() : $currentPeriodStart->copy();
        $previousStart = $reportType === 'YTD' ? $previousYearStart->copy() : $previousPeriodStart->copy();

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
            ->whereNotIn('journal_entries.journal_type', ['opening', 'closing'])
            ->tap($scope)
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $currentMap = $buildBalanceMap($currentStart, $currentPeriodEnd);
        $previousMap = $buildBalanceMap($previousStart, $previousPeriodEnd);

        $accounts = ChartOfAccount::query()
            ->select('chart_of_accounts.id', 'chart_of_accounts.company_id', 'chart_of_accounts.parent_id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.level', 'chart_of_accounts.normal_balance', 'chart_of_accounts.is_active', 'account_groups.type as account_group_type')
            ->leftJoin('account_groups', 'account_groups.id', '=', 'chart_of_accounts.account_group_id')
            ->where('chart_of_accounts.is_active', true)
            ->where('chart_of_accounts.level', 4)
            ->whereIn('account_groups.type', ['revenue', 'cogs', 'expense', 'other_income', 'other_expense'])
            ->with([
                'parent:id,parent_id,code,name,level',
                'parent.parent:id,parent_id,code,name,level',
                'parent.parent.parent:id,parent_id,code,name,level',
            ])
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('chart_of_accounts.company_id', $request->user()->company_id))
            ->when($companyId !== 'all', fn (Builder $query) => $query->where('chart_of_accounts.company_id', $companyId))
            ->orderBy('chart_of_accounts.code')
            ->get();

        $baseRows = $accounts->map(function (ChartOfAccount $account) use ($currentMap, $previousMap) {
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
                'account_group_type' => $account->account_group_type,
                'current_year' => $currentAmount,
                'previous_year' => $previousAmount,
                'variance' => $variance,
            ];
        })->values();

        $groupByKeys = [
            1 => ['coa_level_1_id', 'coa_level_1'],
            2 => ['coa_level_1_id', 'coa_level_1', 'coa_level_2_id', 'coa_level_2'],
            3 => ['coa_level_1_id', 'coa_level_1', 'coa_level_2_id', 'coa_level_2', 'coa_level_3_id', 'coa_level_3'],
            4 => ['coa_level_1_id', 'coa_level_1', 'coa_level_2_id', 'coa_level_2', 'coa_level_3_id', 'coa_level_3', 'coa_level_4_id', 'coa_level_4', 'coa_code'],
        ];

        $rows = $baseRows
            ->groupBy(function (array $row) use ($groupByKeys, $drillLevel) {
                return collect($groupByKeys[$drillLevel])->map(fn ($key) => (string) ($row[$key] ?? ''))->implode('|');
            })
            ->map(function ($items) use ($drillLevel) {
                $first = $items->first();

                return [
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
            ->values();

        $totalSalesCurrent = (float) $baseRows->where('account_group_type', 'revenue')->sum('current_year');
        $totalSalesPrevious = (float) $baseRows->where('account_group_type', 'revenue')->sum('previous_year');
        $totalSalesVariance = $totalSalesCurrent - $totalSalesPrevious;
        $totalExpensesCurrent = (float) $baseRows
            ->whereIn('account_group_type', ['cogs', 'expense', 'other_income', 'other_expense'])
            ->sum('current_year');
        $totalExpensesPrevious = (float) $baseRows
            ->whereIn('account_group_type', ['cogs', 'expense', 'other_income', 'other_expense'])
            ->sum('previous_year');
        $totalExpensesVariance = $totalExpensesCurrent - $totalExpensesPrevious;

        $netProfitCurrent = $totalSalesCurrent - $totalExpensesCurrent;
        $netProfitPrevious = $totalSalesPrevious - $totalExpensesPrevious;
        $netProfitVariance = $netProfitCurrent - $netProfitPrevious;

        $netProfitMarginCurrent = abs($totalSalesCurrent) > 0.000001
            ? ($netProfitCurrent / $totalSalesCurrent) * 100
            : 0;
        $netProfitMarginPrevious = abs($totalSalesPrevious) > 0.000001
            ? ($netProfitPrevious / $totalSalesPrevious) * 100
            : 0;
        $netProfitMarginVariance = abs($totalSalesVariance) > 0.000001
            ? ($netProfitVariance / $totalSalesVariance) * 100
            : 0;

        $rows = $rows->map(function (array $row) use ($totalSalesCurrent, $totalSalesPrevious, $totalSalesVariance) {
            $currentPercent = abs($totalSalesCurrent) > 0.000001
                ? ($row['current_year'] / $totalSalesCurrent) * 100
                : 0;
            $previousPercent = abs($totalSalesPrevious) > 0.000001
                ? ($row['previous_year'] / $totalSalesPrevious) * 100
                : 0;
            $variancePercent = abs($totalSalesVariance) > 0.000001
                ? ($row['variance'] / $totalSalesVariance) * 100
                : 0;

            return [
                ...$row,
                'current_year_percent_sales' => $currentPercent,
                'previous_year_percent_sales' => $previousPercent,
                'variance_percent_sales' => $variancePercent,
            ];
        })->values();

        $summary = [
            'current_year' => (float) $rows->sum('current_year'),
            'previous_year' => (float) $rows->sum('previous_year'),
            'variance' => (float) $rows->sum('variance'),
            'total_sales_current_year' => $totalSalesCurrent,
            'total_sales_previous_year' => $totalSalesPrevious,
            'total_sales_variance' => $totalSalesVariance,
            'total_expenses_current_year' => $totalExpensesCurrent,
            'total_expenses_previous_year' => $totalExpensesPrevious,
            'total_expenses_variance' => $totalExpensesVariance,
            'net_profit_current_year' => $netProfitCurrent,
            'net_profit_previous_year' => $netProfitPrevious,
            'net_profit_variance' => $netProfitVariance,
            'net_profit_margin_current_year' => $netProfitMarginCurrent,
            'net_profit_margin_previous_year' => $netProfitMarginPrevious,
            'net_profit_margin_variance' => $netProfitMarginVariance,
        ];

        return [
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
                'type' => $reportType,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'status' => $status,
                'year' => $year,
                'period' => $period,
                'drill_level' => $drillLevel,
            ],
        ];
    }
}
