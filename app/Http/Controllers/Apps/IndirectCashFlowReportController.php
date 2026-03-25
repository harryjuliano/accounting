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

class IndirectCashFlowReportController extends Controller
{
    use InteractsWithCompanyScope;

    public function __invoke(Request $request)
    {
        $report = $this->buildReport($request);
        $export = strtolower($request->string('export')->toString());

        if ($export === 'pdf') {
            return response()->view('reports.indirect-cash-flow.pdf', [
                ...$report,
                'generatedAt' => now()->locale('id')->translatedFormat('d F Y'),
            ]);
        }

        return inertia('Apps/Reports/IndirectCashFlow/Index', $report);
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

        $status = strtolower($request->string('status')->toString() ?: 'posted');
        $allowedStatuses = ['all', 'draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'posted';
        }

        $scope = fn (Builder $query) => $query
            ->when($companyId !== 'all', fn (Builder $q) => $q->where('journal_entries.company_id', $companyId))
            ->when($branchId !== 'all', fn (Builder $q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($status !== 'all', fn (Builder $q) => $q->where('journal_entries.status', $status));

        $currentYearStart = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $currentPeriodEnd = Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->endOfMonth();
        $currentYearBeginning = $currentYearStart->copy()->subDay();

        $previousYear = $year - 1;
        $previousYearStart = Carbon::create($previousYear, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $previousPeriodEnd = Carbon::create($previousYear, $period, 1, 0, 0, 0, $timezone)->endOfMonth();
        $previousYearBeginning = $previousYearStart->copy()->subDay();

        $accountMeta = ChartOfAccount::query()
            ->select('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_accounts.normal_balance', 'account_groups.type as account_group_type')
            ->join('account_groups', 'account_groups.id', '=', 'chart_of_accounts.account_group_id')
            ->where('chart_of_accounts.level', 4)
            ->where('chart_of_accounts.is_active', true)
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('chart_of_accounts.company_id', $request->user()->company_id))
            ->when($companyId !== 'all', fn (Builder $query) => $query->where('chart_of_accounts.company_id', $companyId))
            ->get()
            ->keyBy('id');

        $buildBalanceMap = fn (Carbon $to) => JournalLine::query()
            ->selectRaw('journal_lines.account_id')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('journal_entries.company_id', $request->user()->company_id))
            ->whereDate('journal_entries.posting_date', '<=', $to->toDateString())
            ->whereNotIn('journal_entries.journal_type', ['closing'])
            ->tap($scope)
            ->groupBy('journal_lines.account_id')
            ->get()
            ->mapWithKeys(function ($item) use ($accountMeta) {
                $meta = $accountMeta->get($item->account_id);
                if (! $meta) {
                    return [];
                }

                $debit = (float) $item->debit;
                $credit = (float) $item->credit;
                $amount = $meta->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

                return [$item->account_id => [
                    'name' => $meta->name,
                    'type' => $meta->account_group_type,
                    'amount' => $amount,
                ]];
            });

        $buildProfit = fn (Carbon $from, Carbon $to) => (float) JournalLine::query()
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit - journal_lines.base_currency_debit), 0) as amount')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->join('account_groups', 'account_groups.id', '=', 'chart_of_accounts.account_group_id')
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('journal_entries.company_id', $request->user()->company_id))
            ->whereDate('journal_entries.posting_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $to->toDateString())
            ->whereNotIn('journal_entries.journal_type', ['opening', 'closing'])
            ->whereIn('account_groups.type', ['revenue', 'cogs', 'expense', 'other_income', 'other_expense'])
            ->tap($scope)
            ->value('amount');

        $currentStartBalances = $buildBalanceMap($currentYearBeginning);
        $currentEndBalances = $buildBalanceMap($currentPeriodEnd);
        $previousStartBalances = $buildBalanceMap($previousYearBeginning);
        $previousEndBalances = $buildBalanceMap($previousPeriodEnd);

        $calcDelta = function ($startMap, $endMap, array $keywords, array $types = []) {
            $match = function (array $row) use ($keywords, $types) {
                $name = strtolower($row['name'] ?? '');
                $typeMatch = empty($types) || in_array($row['type'], $types, true);
                $nameMatch = empty($keywords);
                foreach ($keywords as $keyword) {
                    if (str_contains($name, strtolower($keyword))) {
                        $nameMatch = true;
                        break;
                    }
                }

                return $typeMatch && $nameMatch;
            };

            $allIds = collect($startMap->keys())->merge($endMap->keys())->unique();
            $delta = 0.0;
            foreach ($allIds as $id) {
                $end = $endMap->get($id);
                $start = $startMap->get($id);
                $row = $end ?? $start;
                if (! $row || ! $match($row)) {
                    continue;
                }

                $delta += (float) ($end['amount'] ?? 0) - (float) ($start['amount'] ?? 0);
            }

            return $delta;
        };

        $netSalesCurrent = $buildProfit($currentYearStart, $currentPeriodEnd);
        $netSalesPrevious = $buildProfit($previousYearStart, $previousPeriodEnd);

        $rows = [
            ['section' => 'operating', 'subgroup' => 'profit_base', 'label' => 'Laba Bersih Setelah Pajak', 'current' => $netSalesCurrent, 'previous' => $netSalesPrevious],
            ['section' => 'operating', 'subgroup' => 'non_cash_adjustment', 'label' => 'Penyusutan dan Amortisasi', 'current' => $calcDelta($currentStartBalances, $currentEndBalances, ['penyusutan', 'depresiasi', 'amortisasi'], ['expense']), 'previous' => $calcDelta($previousStartBalances, $previousEndBalances, ['penyusutan', 'depresiasi', 'amortisasi'], ['expense'])],
            ['section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Piutang Usaha', 'current' => -$calcDelta($currentStartBalances, $currentEndBalances, ['piutang usaha', 'accounts receivable'], ['asset']), 'previous' => -$calcDelta($previousStartBalances, $previousEndBalances, ['piutang usaha', 'accounts receivable'], ['asset'])],
            ['section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Persediaan', 'current' => -$calcDelta($currentStartBalances, $currentEndBalances, ['persediaan', 'inventory'], ['asset']), 'previous' => -$calcDelta($previousStartBalances, $previousEndBalances, ['persediaan', 'inventory'], ['asset'])],
            ['section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Utang Usaha', 'current' => $calcDelta($currentStartBalances, $currentEndBalances, ['utang usaha', 'accounts payable'], ['liability']), 'previous' => $calcDelta($previousStartBalances, $previousEndBalances, ['utang usaha', 'accounts payable'], ['liability'])],
            ['section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Utang Pajak', 'current' => $calcDelta($currentStartBalances, $currentEndBalances, ['utang pajak', 'tax payable'], ['liability']), 'previous' => $calcDelta($previousStartBalances, $previousEndBalances, ['utang pajak', 'tax payable'], ['liability'])],
            ['section' => 'investing', 'subgroup' => 'capex', 'label' => 'Pembelian Aset Tetap', 'current' => -$calcDelta($currentStartBalances, $currentEndBalances, ['aset tetap', 'kendaraan', 'peralatan', 'mesin'], ['asset']), 'previous' => -$calcDelta($previousStartBalances, $previousEndBalances, ['aset tetap', 'kendaraan', 'peralatan', 'mesin'], ['asset'])],
            ['section' => 'financing', 'subgroup' => 'borrowing', 'label' => 'Perubahan Pinjaman Bank', 'current' => $calcDelta($currentStartBalances, $currentEndBalances, ['utang bank', 'pinjaman bank'], ['liability']), 'previous' => $calcDelta($previousStartBalances, $previousEndBalances, ['utang bank', 'pinjaman bank'], ['liability'])],
            ['section' => 'financing', 'subgroup' => 'equity', 'label' => 'Perubahan Modal Disetor', 'current' => $calcDelta($currentStartBalances, $currentEndBalances, ['modal', 'setoran modal'], ['equity']), 'previous' => $calcDelta($previousStartBalances, $previousEndBalances, ['modal', 'setoran modal'], ['equity'])],
            ['section' => 'financing', 'subgroup' => 'equity', 'label' => 'Pembagian Dividen', 'current' => -abs($calcDelta($currentStartBalances, $currentEndBalances, ['dividen', 'dividend'], ['equity'])), 'previous' => -abs($calcDelta($previousStartBalances, $previousEndBalances, ['dividen', 'dividend'], ['equity']))],
        ];

        $netIncreaseCurrent = collect($rows)->sum('current');
        $netIncreasePrevious = collect($rows)->sum('previous');

        $beginningCashCurrent = $calcDelta(collect(), $currentStartBalances, ['kas', 'bank', 'cash'], ['asset']);
        $beginningCashPrevious = $calcDelta(collect(), $previousStartBalances, ['kas', 'bank', 'cash'], ['asset']);

        $endingCashCurrent = $beginningCashCurrent + $netIncreaseCurrent;
        $endingCashPrevious = $beginningCashPrevious + $netIncreasePrevious;

        return [
            'companies' => Company::query()
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('id', $request->user()->company_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'branches' => Branch::query()
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name']),
            'statusOptions' => collect($allowedStatuses)->map(fn ($value) => [
                'value' => $value,
                'label' => ucfirst(str_replace('_', ' ', $value)),
            ])->values(),
            'yearOptions' => $yearOptions,
            'filters' => [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'status' => $status,
                'year' => $year,
                'period' => $period,
                'periodLabel' => Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->locale('id')->translatedFormat('F Y'),
            ],
            'report' => [
                'company' => ['name' => $request->user()?->company?->name ?? config('app.name')],
                'filters' => ['year' => $year, 'periodLabel' => Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->locale('id')->translatedFormat('F Y'), 'status' => strtoupper($status)],
                'generatedAt' => now()->locale('id')->translatedFormat('d F Y'),
                'netSales' => ['current' => $netSalesCurrent, 'previous' => $netSalesPrevious],
                'rows' => $rows,
                'beginningCash' => ['current' => $beginningCashCurrent, 'previous' => $beginningCashPrevious],
                'endingCash' => ['current' => $endingCashCurrent, 'previous' => $endingCashPrevious],
            ],
        ];
    }
}
