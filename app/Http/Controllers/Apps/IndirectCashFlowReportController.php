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

        $rowDefinitions = [
            ['key' => 'profit_base', 'section' => 'operating', 'subgroup' => 'profit_base', 'label' => 'Laba Bersih Setelah Pajak'],
            ['key' => 'depreciation', 'section' => 'operating', 'subgroup' => 'non_cash_adjustment', 'label' => 'Penyusutan dan Amortisasi'],
            ['key' => 'receivable', 'section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Piutang Usaha'],
            ['key' => 'inventory', 'section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Persediaan'],
            ['key' => 'account_payable', 'section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Utang Usaha'],
            ['key' => 'tax_payable', 'section' => 'operating', 'subgroup' => 'working_capital', 'label' => 'Kenaikan Utang Pajak'],
            ['key' => 'fixed_asset', 'section' => 'investing', 'subgroup' => 'capex', 'label' => 'Pembelian Aset Tetap'],
            ['key' => 'bank_loan', 'section' => 'financing', 'subgroup' => 'borrowing', 'label' => 'Perubahan Pinjaman Bank'],
            ['key' => 'paid_in_capital', 'section' => 'financing', 'subgroup' => 'equity', 'label' => 'Perubahan Modal Disetor'],
            ['key' => 'dividend', 'section' => 'financing', 'subgroup' => 'equity', 'label' => 'Pembagian Dividen'],
        ];

        $rows = array_map(fn (array $definition) => [
            'section' => $definition['section'],
            'subgroup' => $definition['subgroup'],
            'label' => $definition['label'],
            'values' => [],
        ], $rowDefinitions);

        $months = [];
        $netIncreaseByMonth = [];
        $beginningCashByMonth = [];
        $endingCashByMonth = [];

        foreach (range(1, 12) as $month) {
            $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $monthBeginning = $monthStart->copy()->subDay();

            $startBalances = $buildBalanceMap($monthBeginning);
            $endBalances = $buildBalanceMap($monthEnd);
            $netSales = $buildProfit($monthStart, $monthEnd);

            $amountByKey = [
                'profit_base' => $netSales,
                'depreciation' => $calcDelta($startBalances, $endBalances, ['penyusutan', 'depresiasi', 'amortisasi'], ['expense']),
                'receivable' => -$calcDelta($startBalances, $endBalances, ['piutang usaha', 'accounts receivable'], ['asset']),
                'inventory' => -$calcDelta($startBalances, $endBalances, ['persediaan', 'inventory'], ['asset']),
                'account_payable' => $calcDelta($startBalances, $endBalances, ['utang usaha', 'accounts payable'], ['liability']),
                'tax_payable' => $calcDelta($startBalances, $endBalances, ['utang pajak', 'tax payable'], ['liability']),
                'fixed_asset' => -$calcDelta($startBalances, $endBalances, ['aset tetap', 'kendaraan', 'peralatan', 'mesin'], ['asset']),
                'bank_loan' => $calcDelta($startBalances, $endBalances, ['utang bank', 'pinjaman bank'], ['liability']),
                'paid_in_capital' => $calcDelta($startBalances, $endBalances, ['modal', 'setoran modal'], ['equity']),
                'dividend' => -abs($calcDelta($startBalances, $endBalances, ['dividen', 'dividend'], ['equity'])),
            ];

            foreach ($rowDefinitions as $index => $definition) {
                $rows[$index]['values'][] = (float) ($amountByKey[$definition['key']] ?? 0);
            }

            $netIncreaseByMonth[] = (float) array_sum($amountByKey);
            $beginningCashByMonth[] = $calcDelta(collect(), $startBalances, ['kas', 'bank', 'cash'], ['asset']);
            $endingCashByMonth[] = $calcDelta(collect(), $endBalances, ['kas', 'bank', 'cash'], ['asset']);

            $months[] = [
                'value' => $month,
                'label' => Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->locale('id')->translatedFormat('M'),
            ];
        }

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
            ],
            'report' => [
                'company' => ['name' => $request->user()?->company?->name ?? config('app.name')],
                'filters' => ['year' => $year, 'status' => strtoupper($status)],
                'generatedAt' => now()->locale('id')->translatedFormat('d F Y'),
                'months' => $months,
                'rows' => $rows,
                'netIncreaseByMonth' => $netIncreaseByMonth,
                'beginningCashByMonth' => $beginningCashByMonth,
                'endingCashByMonth' => $endingCashByMonth,
            ],
        ];
    }
}
