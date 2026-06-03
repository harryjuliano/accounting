<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualJournalRequest;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualJournalController extends Controller
{
    use InteractsWithCompanyScope;

    private function buildJournalLinePayload(array $line, int $lineNo, string $currencyCode, float $exchangeRate): array
    {
        $debit = (float) ($line['debit'] ?? 0);
        $credit = (float) ($line['credit'] ?? 0);
        $originalAmount = $debit > 0 ? $debit : $credit;

        return [
            'line_no' => $lineNo,
            'account_id' => $line['account_id'],
            'description' => $line['description'] ?? null,
            'item_code' => $line['item_code'] ?? null,
            'item_name' => $line['item_name'] ?? null,
            'quantity' => isset($line['quantity']) && $line['quantity'] !== '' ? (float) $line['quantity'] : null,
            'quantity_uom' => $line['quantity_uom'] ?? null,
            'debit' => $debit,
            'credit' => $credit,
            'original_currency_code' => $currencyCode,
            'original_currency_amount' => $originalAmount,
            'base_currency_debit' => round($debit * $exchangeRate, 2),
            'base_currency_credit' => round($credit * $exchangeRate, 2),
            'dimension_details_json' => $line['dimension_details'] ?? null,
        ];
    }

    private function resolveAccountingPeriod(int $companyId, string $postingDate): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->with('fiscalYear:id,status')
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $postingDate)
            ->whereDate('end_date', '>=', $postingDate)
            ->first();
    }

    private function ensurePeriodAllowsPosting(AccountingPeriod $accountingPeriod): void
    {
        if ($accountingPeriod->fiscalYear?->status === 'closed') {
            throw ValidationException::withMessages([
                'posting_date' => 'Tahun fiskal sudah hard close. Posting jurnal untuk tahun ini tidak diizinkan.',
            ]);
        }

        if ($accountingPeriod->status !== 'open') {
            throw ValidationException::withMessages([
                'posting_date' => 'Periode bulanan sudah soft/hard close. Posting jurnal pada periode ini tidak diizinkan.',
            ]);
        }
    }

    public function index(Request $request)
    {
        $userTimezone = $request->user()?->company?->timezone ?? config('app.timezone', 'UTC');
        $today = Carbon::now($userTimezone);

        $year = (int) $request->integer('year', (int) $today->year);
        $monthFilter = (string) $request->input('month', (string) $today->month);
        $isAllMonths = $monthFilter === 'all';
        $month = $isAllMonths ? 'all' : max(1, min(12, (int) $monthFilter));
        $branchId = $request->input('branch_id', 'all');

        $statusFilter = $request->input('status', 'all');

        $sortBy = $request->string('sort_by')->toString() ?: 'posting_date';
        $sortDirection = strtolower($request->string('sort_direction')->toString() ?: 'desc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $sortColumns = [
            'id' => 'journal_entries.id',
            'company' => 'companies.name',
            'branch' => 'branches.code',
            'journal_no' => 'journal_entries.journal_no',
            'posting_date' => 'journal_entries.posting_date',
            'description' => 'journal_entries.description',
            'currency' => 'journal_entries.currency_code',
            'original_amount' => 'journal_entries.total_debit',
            'report_amount' => DB::raw('(journal_entries.total_debit * journal_entries.exchange_rate)'),
            'status' => 'journal_entries.status',
        ];

        $resolvedSortColumn = $sortColumns[$sortBy] ?? $sortColumns['posting_date'];

        $manualJournals = JournalEntry::query()
            ->select('journal_entries.*')
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->leftJoin('branches', 'branches.id', '=', 'journal_entries.branch_id')
            ->with(['company:id,name,timezone', 'branch:id,company_id,code,name', 'accountingPeriod:id,period_name', 'lines.account:id,company_id,code,name,requires_dimension'])
            ->where('journal_type', 'manual')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('journal_entries.company_id', $request->user()->company_id))
            ->whereYear('journal_entries.posting_date', $year)
            ->when(! $isAllMonths, fn ($query) => $query->whereMonth('journal_entries.posting_date', $month))
            ->when($branchId !== 'all', fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->when($statusFilter !== 'all', fn ($query) => $query->where('journal_entries.status', $statusFilter))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('journal_no', 'like', '%' . $request->search . '%')
                        ->orWhere('reference_no', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%')
                        ->orWhere('entry_date', 'like', '%' . $request->search . '%')
                        ->orWhere('posting_date', 'like', '%' . $request->search . '%')
                        ->orWhere('currency_code', 'like', '%' . $request->search . '%')
                        ->orWhere('status', 'like', '%' . $request->search . '%')
                        ->orWhereRaw('CAST(journal_entries.total_debit AS CHAR) like ?', ['%' . $request->search . '%'])
                        ->orWhereRaw('CAST((journal_entries.total_debit * journal_entries.exchange_rate) AS CHAR) like ?', ['%' . $request->search . '%'])
                        ->orWhereHas('branch', fn ($branchQuery) => $branchQuery
                            ->where('code', 'like', '%' . $request->search . '%')
                            ->orWhere('name', 'like', '%' . $request->search . '%'))
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->orderBy($resolvedSortColumn, $sortDirection)
            ->orderBy('journal_entries.id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $years = JournalEntry::query()
            ->selectRaw('DISTINCT YEAR(posting_date) as year')
            ->where('journal_type', 'manual')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->values();

        if (! $years->contains($year)) {
            $years->prepend($year);
        }

        $deepLinkJournalId = $request->integer('edit_journal_id');
        $deepLinkJournal = null;
        if ($deepLinkJournalId > 0) {
            $deepLinkJournal = JournalEntry::query()
                ->with(['company:id,name,timezone', 'branch:id,company_id,code,name', 'accountingPeriod:id,period_name', 'lines.account:id,company_id,code,name,requires_dimension'])
                ->where('journal_type', 'manual')
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->find($deepLinkJournalId);
        }

        return inertia('Apps/ManualJournals/Index', [
            'manualJournals' => $manualJournals,
            'deepLinkJournal' => $deepLinkJournal,
            'companies' => $this->getAccessibleCompanies(),
            'branches' => Branch::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'accountingPeriods' => AccountingPeriod::query()->select('id', 'company_id', 'period_name', 'start_date', 'end_date')
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->orderByDesc('start_date')->get(),
            'currencies' => Currency::query()->select('code', 'name', 'decimal_places')->where('is_active', true)->orderBy('code')->get(),
            'accounts' => ChartOfAccount::query()
                ->select('id', 'company_id', 'code', 'name', 'level', 'requires_dimension')
                ->with(['dimensions:id,company_id,name,type,attribute_schema_json'])
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->where('is_active', true)
                ->where('level', 4)
                ->orderBy('code')
                ->get(),
            'defaultEntryDate' => Carbon::now()->toDateString(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'year' => $year,
                'month' => $month,
                'branch_id' => $branchId,
                'status' => $statusFilter,
            ],
            'sort' => [
                'by' => $sortBy,
                'direction' => $sortDirection,
            ],
            'yearOptions' => $years,
            'monthOptions' => collect(range(1, 12))->map(fn (int $value) => [
                'value' => $value,
                'label' => Carbon::create()->month($value)->translatedFormat('F'),
            ])->prepend([
                'value' => 'all',
                'label' => 'All Month',
            ])->values(),
        ]);
    }

    public function bulkPost(Request $request)
    {
        $validated = $request->validate([
            'journal_ids' => ['required', 'array', 'min:1'],
            'journal_ids.*' => ['integer', 'exists:journal_entries,id'],
        ]);

        $entries = JournalEntry::query()
            ->where('journal_type', 'manual')
            ->whereIn('id', $validated['journal_ids'])
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->get();

        DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                $accountingPeriod = $this->resolveAccountingPeriod((int) $entry->company_id, (string) $entry->posting_date);

                if (! $accountingPeriod) {
                    throw ValidationException::withMessages([
                        'journal_ids' => "Periode fiskal untuk jurnal {$entry->journal_no} tidak ditemukan.",
                    ]);
                }

                $this->ensurePeriodAllowsPosting($accountingPeriod);
                $entry->update(['status' => 'posted']);
            }
        });

        return back();
    }

    public function store(ManualJournalRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $accountingPeriod = $this->resolveAccountingPeriod((int) $validated['company_id'], $validated['posting_date']);

            if (! $accountingPeriod) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
                ]);
            }
            $this->ensurePeriodAllowsPosting($accountingPeriod);

            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $journalEntry = JournalEntry::create([
                'company_id' => $validated['company_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'accounting_period_id' => $accountingPeriod->id,
                'journal_no' => $validated['journal_no'],
                'journal_type' => 'manual',
                'source_module' => 'manual_journal_form',
                'source_module_name' => $validated['source_module_name'] ?? 'Manual Journal',
                'source_event' => 'form_input',
                'source_document_type' => 'manual_journal',
                'entry_date' => $validated['entry_date'],
                'posting_date' => $validated['posting_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'counterparty_type' => $validated['counterparty_type'] ?? null,
                'counterparty_code' => $validated['counterparty_code'] ?? null,
                'counterparty_name' => $validated['counterparty_name'] ?? null,
                'salesperson_code' => $validated['salesperson_code'] ?? null,
                'salesperson_name' => $validated['salesperson_name'] ?? null,
                'description' => $validated['description'],
                'currency_code' => $validated['currency_code'],
                'exchange_rate' => $validated['exchange_rate'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $validated['status'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['lines'] as $index => $line) {
                $journalEntry->lines()->create(
                    $this->buildJournalLinePayload(
                        $line,
                        $index + 1,
                        (string) $validated['currency_code'],
                        (float) $validated['exchange_rate']
                    )
                );
            }
        });

        return back();
    }

    public function update(ManualJournalRequest $request, JournalEntry $manual_journal)
    {
        $this->enforceCompanyAccess((int) $manual_journal->company_id);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $manual_journal) {
            $accountingPeriod = $this->resolveAccountingPeriod((int) $validated['company_id'], $validated['posting_date']);

            if (! $accountingPeriod) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
                ]);
            }
            $this->ensurePeriodAllowsPosting($accountingPeriod);

            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $manual_journal->update([
                'company_id' => $validated['company_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'accounting_period_id' => $accountingPeriod->id,
                'journal_no' => $validated['journal_no'],
                'source_module_name' => $validated['source_module_name'] ?? $manual_journal->source_module_name,
                'entry_date' => $validated['entry_date'],
                'posting_date' => $validated['posting_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'counterparty_type' => $validated['counterparty_type'] ?? null,
                'counterparty_code' => $validated['counterparty_code'] ?? null,
                'counterparty_name' => $validated['counterparty_name'] ?? null,
                'salesperson_code' => $validated['salesperson_code'] ?? null,
                'salesperson_name' => $validated['salesperson_name'] ?? null,
                'description' => $validated['description'],
                'currency_code' => $validated['currency_code'],
                'exchange_rate' => $validated['exchange_rate'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $validated['status'],
            ]);

            $manual_journal->lines()->delete();

            foreach ($validated['lines'] as $index => $line) {
                $manual_journal->lines()->create(
                    $this->buildJournalLinePayload(
                        $line,
                        $index + 1,
                        (string) $validated['currency_code'],
                        (float) $validated['exchange_rate']
                    )
                );
            }
        });

        return back();
    }


    public function importFromCsv(Request $request)
    {
        $maxUploadKb = max(1, (int) config('imports.manual_journals.max_upload_kb', 20 * 1024));
        $maxRows = max(2, (int) config('imports.manual_journals.max_rows', 50000));

        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxUploadKb}"],
        ]);
        $companyId = $this->resolveLoggedInCompanyId($request);

        $rows = $this->parseManualJournalCsv($payload['file']);

        if (count($rows) > $maxRows) {
            throw ValidationException::withMessages([
                'file' => "Jumlah baris CSV melebihi batas {$maxRows} baris per import.",
            ]);
        }

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'file' => 'Minimal berisi 2 baris jurnal agar debit dan kredit seimbang.',
            ]);
        }

        $groupedRows = collect($rows)->groupBy(fn (array $row) => $row['journal_no']);
        $importedJournalCount = 0;
        $importedPostingDates = [];

        DB::transaction(function () use ($groupedRows, $request, $companyId, &$importedJournalCount, &$importedPostingDates): void {
            foreach ($groupedRows as $journalRows) {
                $firstRow = $journalRows->first();
                $journalNo = $firstRow['journal_no'];

                if (! Currency::query()->where('code', $firstRow['currency_code'])->exists()) {
                    throw ValidationException::withMessages([
                        'file' => "Currency {$firstRow['currency_code']} pada jurnal {$journalNo} tidak ditemukan.",
                    ]);
                }

                foreach ($journalRows as $row) {
                    if (
                        $row['entry_date'] !== $firstRow['entry_date']
                        || $row['posting_date'] !== $firstRow['posting_date']
                        || $row['currency_code'] !== $firstRow['currency_code']
                        || $row['exchange_rate'] !== $firstRow['exchange_rate']
                        || $row['status'] !== $firstRow['status']
                        || $row['source_module'] !== $firstRow['source_module']
                        || $row['source_module_name'] !== $firstRow['source_module_name']
                        || $row['source_event'] !== $firstRow['source_event']
                        || $row['counterparty_type'] !== $firstRow['counterparty_type']
                        || $row['counterparty_code'] !== $firstRow['counterparty_code']
                        || $row['counterparty_name'] !== $firstRow['counterparty_name']
                        || $row['salesperson_code'] !== $firstRow['salesperson_code']
                        || $row['salesperson_name'] !== $firstRow['salesperson_name']
                    ) {
                        throw ValidationException::withMessages([
                            'file' => "Data header jurnal {$journalNo} harus konsisten pada setiap baris (tanggal, currency, rate, status, source module, counterparty, salesman).",
                        ]);
                    }
                }

                $existingEntry = JournalEntry::query()
                    ->where('company_id', $companyId)
                    ->where('journal_no', $journalNo)
                    ->exists();

                if ($existingEntry) {
                    throw ValidationException::withMessages([
                        'file' => "Nomor jurnal {$journalNo} sudah terdaftar. Gunakan nomor jurnal lain.",
                    ]);
                }

                $accountingPeriod = $this->resolveAccountingPeriod($companyId, $firstRow['posting_date']);

                if (! $accountingPeriod) {
                    throw ValidationException::withMessages([
                        'file' => "Periode fiskal untuk jurnal {$journalNo} tidak ditemukan.",
                    ]);
                }

                $this->ensurePeriodAllowsPosting($accountingPeriod);

                $branchId = $firstRow['branch_code']
                    ? (int) Branch::query()
                        ->where('company_id', $companyId)
                        ->where('code', $firstRow['branch_code'])
                        ->value('id')
                    : null;

                if ($firstRow['branch_code'] && ! $branchId) {
                    throw ValidationException::withMessages([
                        'file' => "Branch code {$firstRow['branch_code']} pada jurnal {$journalNo} tidak ditemukan.",
                    ]);
                }

                $accountCodes = $journalRows->pluck('account_code')->unique()->values();
                $accounts = ChartOfAccount::query()
                    ->where('company_id', $companyId)
                    ->whereIn('code', $accountCodes)
                    ->get(['id', 'code', 'name', 'is_active', 'allow_manual_posting'])
                    ->keyBy('code');

                $lines = $journalRows->map(function (array $row, int $index) use ($accounts, $journalNo) {
                    $account = $accounts->get($row['account_code']);

                    if (! $account) {
                        throw ValidationException::withMessages([
                            'file' => "Account code {$row['account_code']} pada jurnal {$journalNo} tidak ditemukan.",
                        ]);
                    }

                    if (! $account->is_active || ! $account->allow_manual_posting) {
                        throw ValidationException::withMessages([
                            'file' => "Account code {$row['account_code']} pada jurnal {$journalNo} tidak aktif atau Manual Posting = Tidak.",
                        ]);
                    }

                    return [
                        'line_no' => $index + 1,
                        'account_id' => (int) $account->id,
                        'description' => $row['line_description'] ?: null,
                        'item_code' => $row['item_code'] ?: null,
                        'item_name' => $row['item_name'] ?: null,
                        'quantity' => $row['quantity'] > 0 ? $row['quantity'] : null,
                        'quantity_uom' => $row['quantity_uom'] ?: null,
                        'debit' => $row['debit'],
                        'credit' => $row['credit'],
                        'dimension_details' => $this->buildImportDimensionDetails($row),
                    ];
                })->values();

                $totalDebit = (float) $lines->sum('debit');
                $totalCredit = (float) $lines->sum('credit');

                if (round($totalDebit, 2) !== round($totalCredit, 2) || $totalDebit <= 0 || $totalCredit <= 0) {
                    throw ValidationException::withMessages([
                        'file' => "Jurnal {$journalNo} tidak seimbang. Total debit dan kredit harus sama serta lebih dari 0.",
                    ]);
                }

                if ($lines->contains(fn (array $line) => (($line['debit'] > 0 && $line['credit'] > 0) || ($line['debit'] == 0 && $line['credit'] == 0)))) {
                    throw ValidationException::withMessages([
                        'file' => "Setiap baris pada jurnal {$journalNo} wajib hanya berisi debit atau kredit.",
                    ]);
                }

                $journalEntry = JournalEntry::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'accounting_period_id' => $accountingPeriod->id,
                    'journal_no' => $journalNo,
                    'journal_type' => 'manual',
                    'source_module' => $firstRow['source_module'] ?: 'manual_journal_import',
                    'source_module_name' => $firstRow['source_module_name'] ?: 'Manual Journal Import',
                    'source_event' => $firstRow['source_event'] ?: 'csv_import',
                    'source_document_type' => 'manual_journal_template',
                    'entry_date' => $firstRow['entry_date'],
                    'posting_date' => $firstRow['posting_date'],
                    'reference_no' => $firstRow['reference_no'] ?: null,
                    'counterparty_type' => $firstRow['counterparty_type'] ?: null,
                    'counterparty_code' => $firstRow['counterparty_code'] ?: null,
                    'counterparty_name' => $firstRow['counterparty_name'] ?: null,
                    'salesperson_code' => $firstRow['salesperson_code'] ?: null,
                    'salesperson_name' => $firstRow['salesperson_name'] ?: null,
                    'description' => $firstRow['description'],
                    'currency_code' => $firstRow['currency_code'],
                    'exchange_rate' => $firstRow['exchange_rate'],
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'status' => $firstRow['status'],
                    'created_by' => $request->user()->id,
                ]);

                foreach ($lines as $line) {
                    $journalEntry->lines()->create($this->buildJournalLinePayload(
                        $line,
                        $line['line_no'],
                        (string) $firstRow['currency_code'],
                        (float) $firstRow['exchange_rate']
                    ));
                }

                $importedJournalCount++;
                $importedPostingDates[] = $firstRow['posting_date'];
            }
        });

        $message = "Import manual jurnal berhasil diproses ({$importedJournalCount} jurnal).";

        if ($importedJournalCount > 0) {
            $minPostingDate = collect($importedPostingDates)->min();
            $maxPostingDate = collect($importedPostingDates)->max();

            $message .= $minPostingDate === $maxPostingDate
                ? " Jika data belum terlihat, sesuaikan filter Tahun/Bulan ke periode posting {$minPostingDate}."
                : " Jika data belum terlihat, sesuaikan filter Tahun/Bulan ke rentang posting {$minPostingDate} s.d. {$maxPostingDate}.";
        }

        return back()->with('success', $message);
    }

    public function downloadImportTemplate(Request $request)
    {
        $headers = $this->manualJournalTemplateHeaders();
        $this->resolveLoggedInCompanyId($request);
        $sampleRows = [
            ['JRN-0001', '2026-03-01', '2026-03-01', 'REF-001', 'Penjualan tunai', 'IDR', '1', 'draft', 'JKT', 'sales', 'Modul Penjualan', 'sales_invoice_posted', 'customer', 'CUST-001', 'Customer A', 'SLS-001', 'Budi Sales', '1101', 'Kas', 'BRG-001', 'Barang Contoh', '10', 'PCS', '', '', '1000000', '0'],
            ['JRN-0001', '2026-03-01', '2026-03-01', 'REF-001', 'Penjualan tunai', 'IDR', '1', 'draft', 'JKT', 'sales', 'Modul Penjualan', 'sales_invoice_posted', 'customer', 'CUST-001', 'Customer A', 'SLS-001', 'Budi Sales', '4101', 'Pendapatan penjualan', 'BRG-001', 'Barang Contoh', '10', 'PCS', '', '', '0', '1000000'],
        ];

        $stream = fopen('php://temp', 'wb+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);

        foreach ($sampleRows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="manual-journal-import-template.csv"',
        ]);
    }

    private function manualJournalTemplateHeaders(): array
    {
        return [
            'journal_no',
            'entry_date',
            'posting_date',
            'reference_no',
            'description',
            'currency_code',
            'exchange_rate',
            'status',
            'branch_code',
            'source_module',
            'source_module_name',
            'source_event',
            'counterparty_type',
            'counterparty_code',
            'counterparty_name',
            'salesperson_code',
            'salesperson_name',
            'account_code',
            'line_description',
            'item_code',
            'item_name',
            'quantity',
            'quantity_uom',
            'cost_center_code',
            'cost_center_name',
            'debit',
            'credit',
        ];
    }

    private function requiredManualJournalHeaders(): array
    {
        return [
            'journal_no',
            'entry_date',
            'posting_date',
            'reference_no',
            'description',
            'currency_code',
            'exchange_rate',
            'status',
            'branch_code',
            'account_code',
            'line_description',
            'debit',
            'credit',
        ];
    }

    private function buildImportDimensionDetails(array $row): ?array
    {
        $costCenterCode = $row['cost_center_code'] ?? '';
        $costCenterName = $row['cost_center_name'] ?? '';

        if ($costCenterCode === '' && $costCenterName === '') {
            return null;
        }

        return [
            'cost_center' => [
                'code' => $costCenterCode ?: null,
                'name' => $costCenterName ?: null,
            ],
        ];
    }

    private function parseManualJournalCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $requiredHeaders = $this->requiredManualJournalHeaders();
        $delimiter = $this->detectCsvDelimiter($handle, $requiredHeaders);
        $headers = $this->normalizeCsvHeaders(fgetcsv($handle, 0, $delimiter) ?: []);

        if (array_diff($requiredHeaders, $headers) !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'Format file tidak sesuai template import manual jurnal.',
            ]);
        }

        $rows = [];
        $rowNumber = 1;

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if (count(array_filter($line, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($headers, array_pad($line, count($headers), ''));
            $rows[] = $this->normalizeManualJournalRow($row, $rowNumber);
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeManualJournalRow(array $row, int $rowNumber): array
    {
        $normalized = [
            'journal_no' => trim((string) ($row['journal_no'] ?? '')),
            'entry_date' => $this->normalizeCsvDate((string) ($row['entry_date'] ?? ''), 'entry_date', $rowNumber),
            'posting_date' => $this->normalizeCsvDate((string) ($row['posting_date'] ?? ''), 'posting_date', $rowNumber),
            'reference_no' => trim((string) ($row['reference_no'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'currency_code' => trim((string) ($row['currency_code'] ?? '')),
            'exchange_rate' => $this->normalizeCsvNumber($row['exchange_rate'] ?? '0'),
            'status' => strtolower(trim((string) ($row['status'] ?? 'draft'))),
            'branch_code' => trim((string) ($row['branch_code'] ?? '')),
            'source_module' => trim((string) ($row['source_module'] ?? '')),
            'source_module_name' => trim((string) ($row['source_module_name'] ?? '')),
            'source_event' => trim((string) ($row['source_event'] ?? '')),
            'counterparty_type' => trim((string) ($row['counterparty_type'] ?? '')),
            'counterparty_code' => trim((string) ($row['counterparty_code'] ?? '')),
            'counterparty_name' => trim((string) ($row['counterparty_name'] ?? '')),
            'salesperson_code' => trim((string) ($row['salesperson_code'] ?? '')),
            'salesperson_name' => trim((string) ($row['salesperson_name'] ?? '')),
            'account_code' => trim((string) ($row['account_code'] ?? '')),
            'line_description' => trim((string) ($row['line_description'] ?? '')),
            'item_code' => trim((string) ($row['item_code'] ?? '')),
            'item_name' => trim((string) ($row['item_name'] ?? '')),
            'quantity' => $this->normalizeCsvNumber($row['quantity'] ?? '0'),
            'quantity_uom' => trim((string) ($row['quantity_uom'] ?? '')),
            'cost_center_code' => trim((string) ($row['cost_center_code'] ?? '')),
            'cost_center_name' => trim((string) ($row['cost_center_name'] ?? '')),
            'debit' => $this->normalizeCsvNumber($row['debit'] ?? '0'),
            'credit' => $this->normalizeCsvNumber($row['credit'] ?? '0'),
        ];

        if (
            $normalized['journal_no'] === ''
            || $normalized['entry_date'] === ''
            || $normalized['posting_date'] === ''
            || $normalized['description'] === ''
            || $normalized['currency_code'] === ''
            || $normalized['account_code'] === ''
        ) {
            throw ValidationException::withMessages([
                'file' => "Kolom wajib belum lengkap pada baris {$rowNumber}.",
            ]);
        }

        if (! in_array($normalized['status'], ['draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'file' => "Status pada baris {$rowNumber} tidak valid.",
            ]);
        }

        if ($normalized['exchange_rate'] <= 0) {
            throw ValidationException::withMessages([
                'file' => "exchange_rate harus lebih dari 0 pada baris {$rowNumber}.",
            ]);
        }

        return $normalized;
    }

    private function normalizeCsvDate(string $value, string $field, int $rowNumber): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+(?:\.0+)?$/', $value) === 1) {
            $excelSerial = (int) round((float) $value);

            if ($excelSerial > 0) {
                return Carbon::create(1899, 12, 30)->addDays($excelSerial)->format('Y-m-d');
            }
        }

        foreach (['Y-m-d', 'Y/n/j', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        throw ValidationException::withMessages([
            'file' => "Format {$field} tidak valid pada baris {$rowNumber}. Gunakan format tanggal seperti YYYY-MM-DD atau DD/MM/YYYY.",
        ]);
    }

    private function normalizeCsvNumber(mixed $value): float
    {
        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return 0.0;
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', $rawValue);
        $lowered = strtolower($normalized);

        if (str_contains($lowered, 'e')) {
            return (float) str_replace(',', '.', $normalized);
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }

            return (float) $normalized;
        }

        if (str_contains($normalized, ',')) {
            if (preg_match('/,\d{1,2}$/', $normalized) === 1) {
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }

            return (float) $normalized;
        }

        if (preg_match('/\.\d{3}(\.|$)/', $normalized) === 1 && preg_match('/\.\d{1,2}$/', $normalized) !== 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    private function resolveLoggedInCompanyId(Request $request): int
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        if ($companyId <= 0 || ! Company::query()->where('id', $companyId)->exists()) {
            throw ValidationException::withMessages([
                'file' => 'User login belum terhubung ke company aktif. Hubungi admin untuk set company user sebelum import.',
            ]);
        }

        return $companyId;
    }

    private function detectCsvDelimiter($handle, array $expectedHeaders): string
    {
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            rewind($handle);

            return ',';
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiters = [',', ';', "	", '|'];

        foreach ($delimiters as $delimiter) {
            $candidateHeaders = $this->normalizeCsvHeaders(str_getcsv($firstLine, $delimiter));
            if (array_diff($expectedHeaders, $candidateHeaders) === []) {
                rewind($handle);

                return $delimiter;
            }
        }

        rewind($handle);

        return ',';
    }

    private function normalizeCsvHeaders(array $headers): array
    {
        return array_map(static function ($header) {
            $normalized = trim((string) $header);

            return preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        }, $headers);
    }

    public function destroy(JournalEntry $manual_journal)
    {
        $this->enforceCompanyAccess((int) $manual_journal->company_id);
        $manual_journal->delete();

        return back();
    }
}
