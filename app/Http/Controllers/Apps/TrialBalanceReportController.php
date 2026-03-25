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

class TrialBalanceReportController extends Controller
{
    use InteractsWithCompanyScope;

    public function __invoke(Request $request)
    {
        $report = $this->buildReportData($request);

        return inertia('Apps/Reports/TrialBalance/Index', [
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'yearOptions' => $report['yearOptions'],
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
            'filters' => $report['filters'],
        ]);
    }

    public function export(Request $request)
    {
        $report = $this->buildReportData($request);
        $rows = $report['rows'];
        $summary = $report['summary'];
        $filters = $report['filters'];
        $filename = sprintf('trial-balance-%s-%s.csv', strtolower($filters['type']), now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($rows, $summary) {
            $output = fopen('php://output', 'w');

            fputcsv($output, ['COA Level 1', 'COA Level 2', 'COA Level 3', 'COA Level 4', 'Kode COA', 'Saldo Awal', 'Mutasi Debet', 'Mutasi Kredit', 'Saldo Akhir']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['coa_level_1'] ?? '',
                    $row['coa_level_2'] ?? '',
                    $row['coa_level_3'] ?? '',
                    $row['coa_level_4'] ?? '',
                    $row['coa_code'] ?? '',
                    round((float) $row['opening_balance'], 2),
                    round((float) $row['mutation_debit'], 2),
                    round((float) $row['mutation_credit'], 2),
                    round((float) $row['closing_balance'], 2),
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['TOTAL', '', '', '', '',
                round((float) $summary['opening_balance'], 2),
                round((float) $summary['mutation_debit'], 2),
                round((float) $summary['mutation_credit'], 2),
                round((float) $summary['closing_balance'], 2),
            ]);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildReportData(Request $request): array
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
        $status = strtolower($request->string('status')->toString() ?: 'posted');
        $allowedStatuses = ['all', 'draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'posted';
        }

        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $periodStart = Carbon::create($year, $period, 1, 0, 0, 0, $timezone)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $movementStart = $reportType === 'YTD' ? $yearStart->copy() : $periodStart->copy();

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

        $openingBalanceMap = JournalLine::query()
            ->selectRaw('journal_lines.account_id')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('journal_entries.company_id', $request->user()->company_id))
            ->whereDate('journal_entries.posting_date', '>=', $yearStart->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $yearStart->copy()->endOfYear()->toDateString())
            ->where('journal_entries.journal_type', 'opening')
            ->tap($scope)
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $openingCurrentYearBeforePeriod = $period > 1
            ? $buildBalanceMap($yearStart->copy(), $periodStart->copy()->subDay())
            : collect();

        $movementMap = $buildBalanceMap($movementStart->copy(), $periodEnd->copy());

        $accounts = ChartOfAccount::query()
            ->select('id', 'company_id', 'parent_id', 'code', 'name', 'level', 'is_active')
            ->where('is_active', true)
            ->where('level', 4)
            ->with([
                'parent:id,parent_id,code,name,level',
                'parent.parent:id,parent_id,code,name,level',
                'parent.parent.parent:id,parent_id,code,name,level',
            ])
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
            ->when($companyId !== 'all', fn (Builder $query) => $query->where('company_id', $companyId))
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($openingBalanceMap, $openingCurrentYearBeforePeriod, $movementMap, $reportType) {
            $openingBase = (float) ($openingBalanceMap->get($account->id)?->debit ?? 0) - (float) ($openingBalanceMap->get($account->id)?->credit ?? 0);
            $openingCurrentYear = (float) ($openingCurrentYearBeforePeriod->get($account->id)?->debit ?? 0) - (float) ($openingCurrentYearBeforePeriod->get($account->id)?->credit ?? 0);

            $openingBalance = $reportType === 'YTD'
                ? $openingBase
                : $openingBase + $openingCurrentYear;

            $mutationDebit = (float) ($movementMap->get($account->id)?->debit ?? 0);
            $mutationCredit = (float) ($movementMap->get($account->id)?->credit ?? 0);
            $closingBalance = $openingBalance + $mutationDebit - $mutationCredit;

            $level3 = $account->parent;
            $level2 = $level3?->parent;
            $level1 = $level2?->parent;

            return [
                'coa_id' => $account->id,
                'coa_level_1' => $level1?->name,
                'coa_level_2' => $level2?->name,
                'coa_level_3' => $level3?->name,
                'coa_level_4' => $account->name,
                'coa_code' => $account->code,
                'opening_balance' => $openingBalance,
                'mutation_debit' => $mutationDebit,
                'mutation_credit' => $mutationCredit,
                'closing_balance' => $closingBalance,
            ];
        })->values();


        $summary = [
            'opening_balance' => (float) $rows->sum('opening_balance'),
            'mutation_debit' => (float) $rows->sum('mutation_debit'),
            'mutation_credit' => (float) $rows->sum('mutation_credit'),
            'closing_balance' => (float) $rows->sum('closing_balance'),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
            'yearOptions' => $yearOptions,
            'filters' => [
                'type' => $reportType,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'status' => $status,
                'year' => $year,
                'period' => $period,
            ],
        ];
    }
}
