<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GeneralLedgerReportController extends Controller
{
    use InteractsWithCompanyScope;

    public function __invoke(Request $request)
    {
        $userTimezone = $request->user()?->company?->timezone ?? config('app.timezone', 'UTC');
        $today = Carbon::now($userTimezone)->toDateString();

        $dateFrom = $request->string('date_from')->toString() ?: Carbon::parse($today)->startOfMonth()->toDateString();
        $dateTo = $request->string('date_to')->toString() ?: $today;
        $companyId = $request->input('company_id', 'all');
        $branchId = $request->input('branch_id', 'all');
        $coaId = $request->input('coa_id', 'all');
        $search = $request->string('search')->toString();

        $sortBy = $request->string('sort_by')->toString() ?: 'date';
        $sortDirection = strtolower($request->string('sort_direction')->toString() ?: 'asc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc';

        $sortColumns = [
            'no' => 'journal_entries.id',
            'date' => 'journal_entries.posting_date',
            'company' => 'companies.name',
            'branch' => 'branches.code',
            'journal_no' => 'journal_entries.journal_no',
            'reference' => 'journal_entries.reference_no',
            'header_description' => 'journal_entries.description',
            'currency' => 'journal_lines.original_currency_code',
            'original_amount' => 'journal_lines.original_currency_amount',
            'debit' => 'journal_lines.base_currency_debit',
            'credit' => 'journal_lines.base_currency_credit',
            'detail_description' => 'journal_lines.description',
        ];

        $resolvedSortColumn = $sortColumns[$sortBy] ?? $sortColumns['date'];

        $ledgerLines = JournalLine::query()
            ->select('journal_lines.*')
            ->selectRaw('journal_entries.id as journal_entry_id')
            ->selectRaw('journal_entries.posting_date as posting_date')
            ->selectRaw('journal_entries.journal_no as journal_no')
            ->selectRaw('journal_entries.reference_no as reference_no')
            ->selectRaw('journal_entries.description as header_description')
            ->selectRaw('companies.name as company_name')
            ->selectRaw('branches.code as branch_code')
            ->selectRaw('branches.name as branch_name')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->leftJoin('branches', 'branches.id', '=', 'journal_entries.branch_id')
            ->with('account:id,company_id,code,name')
            ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('journal_entries.company_id', $request->user()->company_id))
            ->whereDate('journal_entries.posting_date', '>=', $dateFrom)
            ->whereDate('journal_entries.posting_date', '<=', $dateTo)
            ->when($companyId !== 'all', fn (Builder $query) => $query->where('journal_entries.company_id', $companyId))
            ->when($branchId !== 'all', fn (Builder $query) => $query->where('journal_entries.branch_id', $branchId))
            ->when($coaId !== 'all', fn (Builder $query) => $query->where('journal_lines.account_id', $coaId))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('journal_entries.journal_no', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.reference_no', 'like', '%' . $search . '%')
                        ->orWhere('journal_entries.description', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.description', 'like', '%' . $search . '%')
                        ->orWhere('journal_lines.original_currency_code', 'like', '%' . $search . '%')
                        ->orWhere('companies.name', 'like', '%' . $search . '%')
                        ->orWhere('branches.code', 'like', '%' . $search . '%')
                        ->orWhere('branches.name', 'like', '%' . $search . '%')
                        ->orWhereHas('account', fn (Builder $accountQuery) => $accountQuery
                            ->where('code', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy($resolvedSortColumn, $sortDirection)
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.line_no')
            ->paginate(20)
            ->withQueryString();

        $runningBalance = 0.0;
        $ledgerLines->setCollection(
            $ledgerLines->getCollection()->map(function (JournalLine $line, int $index) use (&$runningBalance, $ledgerLines) {
                $debit = (float) $line->base_currency_debit;
                $credit = (float) $line->base_currency_credit;
                $runningBalance += ($debit - $credit);

                return [
                    'no' => (($ledgerLines->currentPage() - 1) * $ledgerLines->perPage()) + $index + 1,
                    'date' => $line->posting_date ? Carbon::parse((string) $line->posting_date)->toDateString() : null,
                    'company' => $line->company_name,
                    'branch' => trim(($line->branch_code ? $line->branch_code . ' - ' : '') . ($line->branch_name ?? '-')),
                    'journal_no' => $line->journal_no,
                    'reference' => $line->reference_no,
                    'header_description' => $line->header_description,
                    'currency' => $line->original_currency_code,
                    'original_amount' => (float) $line->original_currency_amount,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $runningBalance,
                    'detail_description' => $line->description,
                    'coa' => trim(($line->account?->code ? $line->account->code . ' - ' : '') . ($line->account?->name ?? '-')),
                ];
            })
        );

        return inertia('Apps/Reports/GeneralLedger/Index', [
            'ledgerLines' => $ledgerLines,
            'companies' => $this->getAccessibleCompanies(),
            'branches' => Branch::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'accounts' => ChartOfAccount::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->where('level', 4)
                ->when($this->isCompanyAdmin(), fn (Builder $query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'coa_id' => $coaId,
                'search' => $search,
            ],
            'sort' => [
                'by' => $sortBy,
                'direction' => $sortDirection,
            ],
        ]);
    }
}
