<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
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
        if (strtolower($request->string('export')->toString()) === 'pdf') {
            $request->merge(['drill_level' => 3]);
        }

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

        $currentPeriodStart = Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->startOfDay();
        $currentPeriodEnd = $currentPeriodStart->copy()->endOfMonth();
        $currentYearStart = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();

        $previousYear = $year - 1;
        $previousYearStart = Carbon::create($previousYear, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $previousPeriodStart = Carbon::create($previousYear, $period, 1, 0, 0, 0, $timezone)->startOfDay();
        $previousPeriodEnd = $previousPeriodStart->copy()->endOfMonth();

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

        $currentMonthMap = $buildBalanceMap($currentPeriodStart, $currentPeriodEnd);
        $currentYtdMap = $buildBalanceMap($currentYearStart, $currentPeriodEnd);
        $lastYearYtdMap = $buildBalanceMap($previousYearStart, $previousPeriodEnd);

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

        $baseRows = $accounts->map(function (ChartOfAccount $account) use ($currentMonthMap, $currentYtdMap, $lastYearYtdMap) {
            $currentMonthDebit = (float) ($currentMonthMap->get($account->id)?->debit ?? 0);
            $currentMonthCredit = (float) ($currentMonthMap->get($account->id)?->credit ?? 0);
            $currentYtdDebit = (float) ($currentYtdMap->get($account->id)?->debit ?? 0);
            $currentYtdCredit = (float) ($currentYtdMap->get($account->id)?->credit ?? 0);
            $lastYearYtdDebit = (float) ($lastYearYtdMap->get($account->id)?->debit ?? 0);
            $lastYearYtdCredit = (float) ($lastYearYtdMap->get($account->id)?->credit ?? 0);

            $normalBalance = $account->normal_balance === 'credit' ? 'credit' : 'debit';
            $currentMonthAmount = $normalBalance === 'credit'
                ? $currentMonthCredit - $currentMonthDebit
                : $currentMonthDebit - $currentMonthCredit;
            $currentYtdAmount = $normalBalance === 'credit'
                ? $currentYtdCredit - $currentYtdDebit
                : $currentYtdDebit - $currentYtdCredit;
            $lastYearYtdAmount = $normalBalance === 'credit'
                ? $lastYearYtdCredit - $lastYearYtdDebit
                : $lastYearYtdDebit - $lastYearYtdCredit;

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
                'current_month' => $currentMonthAmount,
                'year_to_date' => $currentYtdAmount,
                'last_year_to_date' => $lastYearYtdAmount,
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
                    'coa_id' => $drillLevel >= 4 ? ($first['coa_id'] ?? null) : null,
                    'coa_level_1_id' => $first['coa_level_1_id'] ?? null,
                    'coa_level_2_id' => $drillLevel >= 2 ? ($first['coa_level_2_id'] ?? null) : null,
                    'coa_level_3_id' => $drillLevel >= 3 ? ($first['coa_level_3_id'] ?? null) : null,
                    'coa_level_4_id' => $drillLevel >= 4 ? ($first['coa_level_4_id'] ?? null) : null,
                    'coa_level_1' => $first['coa_level_1'] ?? null,
                    'coa_level_2' => $drillLevel >= 2 ? ($first['coa_level_2'] ?? null) : null,
                    'coa_level_3' => $drillLevel >= 3 ? ($first['coa_level_3'] ?? null) : null,
                    'coa_level_4' => $drillLevel >= 4 ? ($first['coa_level_4'] ?? null) : null,
                    'coa_code' => $drillLevel >= 4 ? ($first['coa_code'] ?? null) : null,
                    'account_group_type' => $first['account_group_type'] ?? null,
                    'current_month' => (float) $items->sum('current_month'),
                    'year_to_date' => (float) $items->sum('year_to_date'),
                    'last_year_to_date' => (float) $items->sum('last_year_to_date'),
                ];
            })
            ->values();

        $sumByType = function (string $type, string $column) use ($baseRows): float {
            return (float) $baseRows->where('account_group_type', $type)->sum($column);
        };

        $totalSalesCurrentMonth = $sumByType('revenue', 'current_month');
        $totalSalesYtd = $sumByType('revenue', 'year_to_date');
        $totalSalesLastYearYtd = $sumByType('revenue', 'last_year_to_date');
        $totalCogsCurrentMonth = $sumByType('cogs', 'current_month');
        $totalCogsYtd = $sumByType('cogs', 'year_to_date');
        $totalCogsLastYearYtd = $sumByType('cogs', 'last_year_to_date');
        $totalOperatingExpenseCurrentMonth = $sumByType('expense', 'current_month');
        $totalOperatingExpenseYtd = $sumByType('expense', 'year_to_date');
        $totalOperatingExpenseLastYearYtd = $sumByType('expense', 'last_year_to_date');
        $totalOtherIncomeCurrentMonth = $sumByType('other_income', 'current_month');
        $totalOtherIncomeYtd = $sumByType('other_income', 'year_to_date');
        $totalOtherIncomeLastYearYtd = $sumByType('other_income', 'last_year_to_date');
        $totalOtherExpenseCurrentMonth = $sumByType('other_expense', 'current_month');
        $totalOtherExpenseYtd = $sumByType('other_expense', 'year_to_date');
        $totalOtherExpenseLastYearYtd = $sumByType('other_expense', 'last_year_to_date');

        $totalExpensesCurrentMonth = (float) ($totalCogsCurrentMonth + $totalOperatingExpenseCurrentMonth + $totalOtherExpenseCurrentMonth - $totalOtherIncomeCurrentMonth);
        $totalExpensesYtd = (float) ($totalCogsYtd + $totalOperatingExpenseYtd + $totalOtherExpenseYtd - $totalOtherIncomeYtd);
        $totalExpensesLastYearYtd = (float) ($totalCogsLastYearYtd + $totalOperatingExpenseLastYearYtd + $totalOtherExpenseLastYearYtd - $totalOtherIncomeLastYearYtd);

        $netProfitCurrentMonth = $totalSalesCurrentMonth - $totalExpensesCurrentMonth;
        $netProfitYtd = $totalSalesYtd - $totalExpensesYtd;
        $netProfitLastYearYtd = $totalSalesLastYearYtd - $totalExpensesLastYearYtd;

        $netProfitMarginCurrentMonth = abs($totalSalesCurrentMonth) > 0.000001
            ? ($netProfitCurrentMonth / $totalSalesCurrentMonth) * 100
            : 0;
        $netProfitMarginYtd = abs($totalSalesYtd) > 0.000001
            ? ($netProfitYtd / $totalSalesYtd) * 100
            : 0;
        $netProfitMarginLastYearYtd = abs($totalSalesLastYearYtd) > 0.000001
            ? ($netProfitLastYearYtd / $totalSalesLastYearYtd) * 100
            : 0;

        $rows = $rows->map(function (array $row) use ($totalSalesCurrentMonth, $totalSalesYtd, $totalSalesLastYearYtd) {
            $currentMonthPercent = abs($totalSalesCurrentMonth) > 0.000001
                ? ($row['current_month'] / $totalSalesCurrentMonth) * 100
                : 0;
            $ytdPercent = abs($totalSalesYtd) > 0.000001
                ? ($row['year_to_date'] / $totalSalesYtd) * 100
                : 0;
            $lastYearYtdPercent = abs($totalSalesLastYearYtd) > 0.000001
                ? ($row['last_year_to_date'] / $totalSalesLastYearYtd) * 100
                : 0;

            return [
                ...$row,
                'current_month_percent_sales' => $currentMonthPercent,
                'year_to_date_percent_sales' => $ytdPercent,
                'last_year_to_date_percent_sales' => $lastYearYtdPercent,
            ];
        })->values();

        $summary = [
            'current_month' => (float) $rows->sum('current_month'),
            'year_to_date' => (float) $rows->sum('year_to_date'),
            'last_year_to_date' => (float) $rows->sum('last_year_to_date'),
            'total_sales_current_month' => $totalSalesCurrentMonth,
            'total_sales_year_to_date' => $totalSalesYtd,
            'total_sales_last_year_to_date' => $totalSalesLastYearYtd,
            'net_profit_current_month' => $netProfitCurrentMonth,
            'net_profit_year_to_date' => $netProfitYtd,
            'net_profit_last_year_to_date' => $netProfitLastYearYtd,
            'net_profit_margin_current_month' => $netProfitMarginCurrentMonth,
            'net_profit_margin_year_to_date' => $netProfitMarginYtd,
            'net_profit_margin_last_year_to_date' => $netProfitMarginLastYearYtd,
        ];

        $selectedCompany = $this->resolveSelectedCompany($request, $companyId);
        $selectedBranch = $branchId !== 'all'
            ? Branch::query()->select('id', 'code', 'name', 'address', 'city')->find($branchId)
            : null;

        return [
            'rows' => $rows,
            'summary' => $summary,
            'companyProfile' => [
                'name' => $selectedCompany?->name ?? 'All Companies',
                'legal_name' => $selectedCompany?->legal_name,
                'tax_id' => $selectedCompany?->tax_id,
                'base_currency_code' => $selectedCompany?->base_currency_code,
                'branch_name' => $selectedBranch?->name,
                'branch_code' => $selectedBranch?->code,
                'branch_address' => $selectedBranch?->address,
                'branch_city' => $selectedBranch?->city,
            ],
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
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'status' => $status,
                'year' => $year,
                'period' => $period,
                'drill_level' => $drillLevel,
            ],
        ];
    }

    private function resolveSelectedCompany(Request $request, mixed $companyId): ?Company
    {
        if ($companyId !== 'all') {
            return Company::query()->find($companyId);
        }

        if ($this->isCompanyAdmin()) {
            return Company::query()->find($request->user()?->company_id);
        }

        return null;
    }
}
