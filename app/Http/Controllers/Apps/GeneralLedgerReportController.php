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
use Illuminate\Support\Facades\DB;

class GeneralLedgerReportController extends Controller
{
    use InteractsWithCompanyScope;

    public function __invoke(Request $request)
    {
        $userTimezone = $request->user()?->company?->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($userTimezone);

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

        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $userTimezone)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear();

        $rawDateFrom = $request->string('date_from')->toString();
        $rawDateTo = $request->string('date_to')->toString();
        $defaultDateFrom = $yearStart->copy();
        $defaultDateTo = $now->year === $year ? $now->copy()->startOfDay() : $yearEnd->copy();

        $dateFrom = $rawDateFrom !== '' ? Carbon::parse($rawDateFrom, $userTimezone)->startOfDay() : $defaultDateFrom;
        $dateTo = $rawDateTo !== '' ? Carbon::parse($rawDateTo, $userTimezone)->startOfDay() : $defaultDateTo;

        // Date range must remain inside the selected year.
        $dateFrom = $dateFrom->lt($yearStart) ? $yearStart->copy() : $dateFrom;
        $dateFrom = $dateFrom->gt($yearEnd) ? $yearEnd->copy() : $dateFrom;
        $dateTo = $dateTo->lt($yearStart) ? $yearStart->copy() : $dateTo;
        $dateTo = $dateTo->gt($yearEnd) ? $yearEnd->copy() : $dateTo;
        if ($dateTo->lt($dateFrom)) {
            $dateTo = $dateFrom->copy();
        }

        $companyId = $request->input('company_id', 'all');
        $branchId = $request->input('branch_id', 'all');
        $coaId = $request->input('coa_id');
        $search = $request->string('search')->toString();
        $status = strtolower($request->string('status')->toString() ?: 'posted');
        $allowedStatuses = ['all', 'draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'posted';
        }

        $sortBy = $request->string('sort_by')->toString() ?: 'date';
        $sortDirection = strtolower($request->string('sort_direction')->toString() ?: 'asc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc';

        $sortColumns = [
            'no' => 'journal_entries.id',
            'date' => 'journal_entries.posting_date',
            'company' => 'companies.name',
            'branch' => 'branches.code',
            'journal_no' => 'journal_entries.journal_no',
            'document_no' => 'journal_entries.source_document_no',
            'reference' => 'journal_entries.reference_no',
            'source_module' => 'journal_entries.source_module',
            'source_module_name' => 'journal_entries.source_module_name',
            'salesperson_code' => 'journal_entries.salesperson_code',
            'salesperson_name' => 'journal_entries.salesperson_name',
            'counterparty_type' => 'journal_entries.counterparty_type',
            'counterparty_code' => 'journal_entries.counterparty_code',
            'counterparty_name' => 'journal_entries.counterparty_name',
            'header_description' => 'journal_entries.description',
            'currency' => 'journal_lines.original_currency_code',
            'original_amount' => 'journal_lines.original_currency_amount',
            'debit' => 'journal_lines.base_currency_debit',
            'credit' => 'journal_lines.base_currency_credit',
            'detail_description' => 'journal_lines.description',
            'coa_code' => 'accounts.code',
            'coa_name' => 'accounts.name',
            'item_code' => 'journal_lines.item_code',
            'item_name' => 'journal_lines.item_name',
            'quantity' => 'journal_lines.quantity',
            'quantity_uom' => 'journal_lines.quantity_uom',
            'cost_center_code' => 'cost_centers.code',
            'cost_center_name' => 'cost_centers.name',
        ];

        $resolvedSortColumn = $sortColumns[$sortBy] ?? $sortColumns['date'];

        $accounts = ChartOfAccount::query()
            ->select('id', 'company_id', 'code', 'name')
            ->where('is_active', true)
            ->where('level', 4)
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
            ->when($companyId !== 'all', fn (Builder $query) => $query->where('company_id', $companyId))
            ->orderBy('code')
            ->get();

        $resolvedCoaId = $coaId ?: ($accounts->first()?->id ? (string) $accounts->first()->id : null);

        $scope = fn (Builder $query) => $query
            ->when($this->isCompanyAdmin(), fn (Builder $q) => $q->where('journal_entries.company_id', $request->user()->company_id))
            ->when($companyId !== 'all', fn (Builder $q) => $q->where('journal_entries.company_id', $companyId))
            ->when($branchId !== 'all', fn (Builder $q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($status !== 'all', fn (Builder $q) => $q->where('journal_entries.status', $status))
            ->when($resolvedCoaId, fn (Builder $q) => $q->where('journal_lines.account_id', $resolvedCoaId));

        $openingEntryBalance = JournalLine::query()
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereDate('journal_entries.posting_date', '>=', $yearStart->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $yearEnd->toDateString())
            ->where('journal_entries.journal_type', 'opening')
            ->tap($scope)
            ->first();

        $yearMovementBeforeFrom = $dateFrom->gt($yearStart)
            ? JournalLine::query()
                ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
                ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->whereDate('journal_entries.posting_date', '>=', $yearStart->toDateString())
                ->whereDate('journal_entries.posting_date', '<=', $dateFrom->copy()->subDay()->toDateString())
                ->whereNotIn('journal_entries.journal_type', ['opening', 'closing'])
                ->tap($scope)
                ->first()
            : null;

        $openingBalance = ((float) ($openingEntryBalance?->debit ?? 0) - (float) ($openingEntryBalance?->credit ?? 0))
            + ((float) ($yearMovementBeforeFrom?->debit ?? 0) - (float) ($yearMovementBeforeFrom?->credit ?? 0));

        $ledgerLinesQuery = JournalLine::query()
            ->select('journal_lines.*')
            ->selectRaw('journal_entries.journal_type as journal_type')
            ->selectRaw('journal_entries.posting_date as posting_date')
            ->selectRaw('journal_entries.journal_no as journal_no')
            ->selectRaw('journal_entries.source_document_no as source_document_no')
            ->selectRaw('journal_entries.reference_no as reference_no')
            ->selectRaw('journal_entries.source_module as source_module')
            ->selectRaw('journal_entries.source_module_name as source_module_name')
            ->selectRaw('journal_entries.counterparty_type as counterparty_type')
            ->selectRaw('journal_entries.counterparty_code as counterparty_code')
            ->selectRaw('journal_entries.counterparty_name as counterparty_name')
            ->selectRaw('journal_entries.salesperson_code as salesperson_code')
            ->selectRaw('journal_entries.salesperson_name as salesperson_name')
            ->selectRaw('journal_entries.description as header_description')
            ->selectRaw('companies.name as company_name')
            ->selectRaw('branches.code as branch_code')
            ->selectRaw('branches.name as branch_name')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('accounts.name as account_name')
            ->selectRaw('cost_centers.code as cost_center_code')
            ->selectRaw('cost_centers.name as cost_center_name')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->leftJoin('branches', 'branches.id', '=', 'journal_entries.branch_id')
            ->leftJoin('chart_of_accounts as accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->leftJoin('dimension_values as cost_centers', 'cost_centers.id', '=', 'journal_lines.dimension_cost_center_id')
            ->whereDate('journal_entries.posting_date', '>=', $dateFrom->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $dateTo->toDateString())
            ->whereNotIn('journal_entries.journal_type', ['opening', 'closing'])
            ->tap($scope)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('journal_entries.journal_no', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.source_document_no', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.reference_no', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.source_module', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.source_module_name', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.counterparty_type', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.counterparty_code', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.counterparty_name', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.salesperson_code', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.salesperson_name', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.description', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.description', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.item_code', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.item_name', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.quantity_uom', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.original_currency_code', 'like', '%' . $search . '%')
                        ->orWhere('companies.name', 'like', '%' . $search . '%')
                        ->orWhere('branches.code', 'like', '%' . $search . '%')
                        ->orWhere('branches.name', 'like', '%' . $search . '%')
                        ->orWhere('accounts.code', 'like', '%' . $search . '%')
                        ->orWhere('accounts.name', 'like', '%' . $search . '%')
                        ->orWhere('cost_centers.code', 'like', '%' . $search . '%')
                        ->orWhere('cost_centers.name', 'like', '%' . $search . '%');
                });
            });

        $summarySource = (clone $ledgerLinesQuery)->toBase();
        $summaryRow = DB::query()
            ->fromSub($summarySource, 'ledger_lines_filtered')
            ->selectRaw('COALESCE(SUM(ledger_lines_filtered.base_currency_debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(ledger_lines_filtered.base_currency_credit), 0) as total_credit')
            ->first();

        $ledgerLines = $ledgerLinesQuery
            ->orderBy($resolvedSortColumn, $sortDirection)
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.line_no')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'opening_balance' => $openingBalance,
            'total_debit' => (float) ($summaryRow?->total_debit ?? 0),
            'total_credit' => (float) ($summaryRow?->total_credit ?? 0),
        ];
        $summary['closing_balance'] = $summary['opening_balance'] + $summary['total_debit'] - $summary['total_credit'];

        $ledgerLines->setCollection(
            $ledgerLines->getCollection()->map(function (JournalLine $line, int $index) use ($ledgerLines) {
                $debit = (float) $line->base_currency_debit;
                $credit = (float) $line->base_currency_credit;

                $dimensionCostCenter = data_get($line->dimension_details_json ?? [], 'cost_center');
                $costCenterCode = $line->cost_center_code;
                $costCenterName = $line->cost_center_name;

                if (! $costCenterCode && is_array($dimensionCostCenter)) {
                    $costCenterCode = $dimensionCostCenter['code'] ?? null;
                    $costCenterName = $costCenterName ?: ($dimensionCostCenter['name'] ?? null);
                } elseif (! $costCenterCode && is_string($dimensionCostCenter)) {
                    $costCenterCode = $dimensionCostCenter;
                }

                $accountCode = $line->account_code;
                $accountName = $line->account_name;

                return [
                    'no' => (($ledgerLines->currentPage() - 1) * $ledgerLines->perPage()) + $index + 1,
                    'date' => $line->posting_date ? Carbon::parse((string) $line->posting_date)->toDateString() : null,
                    'journal_entry_id' => $line->journal_entry_id ? (int) $line->journal_entry_id : null,
                    'journal_type' => $line->journal_type,
                    'company' => $line->company_name,
                    'branch' => trim(($line->branch_code ? $line->branch_code . ' - ' : '') . ($line->branch_name ?? '-')),
                    'journal_no' => $line->journal_no,
                    'document_no' => $line->source_document_no ?: $line->journal_no,
                    'reference' => $line->reference_no,
                    'source_module' => $line->source_module,
                    'source_module_name' => $line->source_module_name,
                    'counterparty_type' => $line->counterparty_type,
                    'counterparty_code' => $line->counterparty_code,
                    'counterparty_name' => $line->counterparty_name,
                    'salesperson_code' => $line->salesperson_code,
                    'salesperson_name' => $line->salesperson_name,
                    'header_description' => $line->header_description,
                    'currency' => $line->original_currency_code,
                    'original_amount' => (float) $line->original_currency_amount,
                    'debit' => $debit,
                    'credit' => $credit,
                    'detail_description' => $line->description,
                    'description' => $line->description ?: $line->header_description,
                    'coa_code' => $accountCode,
                    'coa_name' => $accountName,
                    'coa' => trim(($accountCode ? $accountCode . ' - ' : '') . ($accountName ?? '-')),
                    'cost_center_code' => $costCenterCode,
                    'cost_center_name' => $costCenterName,
                    'item_code' => $line->item_code,
                    'item_name' => $line->item_name,
                    'quantity' => $line->quantity !== null ? (float) $line->quantity : null,
                    'quantity_uom' => $line->quantity_uom,
                ];
            })
        );

        return inertia('Apps/Reports/GeneralLedger/Index', [
            'ledgerLines' => $ledgerLines,
            'summary' => $summary,
            'yearOptions' => $yearOptions,
            'companies' => $this->getAccessibleCompanies(),
            'branches' => Branch::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'accounts' => $accounts,
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
                'year' => $year,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'coa_id' => $resolvedCoaId,
                'status' => $status,
                'search' => $search,
            ],
            'sort' => [
                'by' => $sortBy,
                'direction' => $sortDirection,
            ],
        ]);
    }
}
