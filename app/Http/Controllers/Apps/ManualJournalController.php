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

        return inertia('Apps/ManualJournals/Index', [
            'manualJournals' => $manualJournals,
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
                'source_event' => 'form_input',
                'source_document_type' => 'manual_journal',
                'entry_date' => $validated['entry_date'],
                'posting_date' => $validated['posting_date'],
                'reference_no' => $validated['reference_no'] ?? null,
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
                'entry_date' => $validated['entry_date'],
                'posting_date' => $validated['posting_date'],
                'reference_no' => $validated['reference_no'] ?? null,
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
                    ) {
                        throw ValidationException::withMessages([
                            'file' => "Data header jurnal {$journalNo} harus konsisten pada setiap baris (tanggal, currency, rate, status).",
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
                    ->get(['id', 'code'])
                    ->keyBy('code');

                $lines = $journalRows->map(function (array $row, int $index) use ($accounts, $journalNo) {
                    $account = $accounts->get($row['account_code']);

                    if (! $account) {
                        throw ValidationException::withMessages([
                            'file' => "Account code {$row['account_code']} pada jurnal {$journalNo} tidak ditemukan.",
                        ]);
                    }

                    return [
                        'line_no' => $index + 1,
                        'account_id' => (int) $account->id,
                        'description' => $row['line_description'] ?: null,
                        'debit' => $row['debit'],
                        'credit' => $row['credit'],
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
                    'source_module' => 'manual_journal_import',
                    'source_event' => 'csv_import',
                    'source_document_type' => 'manual_journal_template',
                    'entry_date' => $firstRow['entry_date'],
                    'posting_date' => $firstRow['posting_date'],
                    'reference_no' => $firstRow['reference_no'] ?: null,
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
            ['JRN-0001', '2026-03-01', '2026-03-01', 'REF-001', 'Penjualan tunai', 'IDR', '1', 'draft', 'JKT', '1101', 'Kas', '1000000', '0'],
            ['JRN-0001', '2026-03-01', '2026-03-01', 'REF-001', 'Penjualan tunai', 'IDR', '1', 'draft', 'JKT', '4101', 'Pendapatan penjualan', '0', '1000000'],
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
            'account_code',
            'line_description',
            'debit',
            'credit',
        ];
    }

    private function parseManualJournalCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $expectedHeaders = $this->manualJournalTemplateHeaders();
        $delimiter = $this->detectCsvDelimiter($handle, $expectedHeaders);
        $headers = $this->normalizeCsvHeaders(fgetcsv($handle, 0, $delimiter) ?: []);

        if ($headers !== $expectedHeaders) {
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

            $row = array_combine($expectedHeaders, array_pad($line, count($expectedHeaders), ''));
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
            'exchange_rate' => (float) trim((string) ($row['exchange_rate'] ?? '0')),
            'status' => strtolower(trim((string) ($row['status'] ?? 'draft'))),
            'branch_code' => trim((string) ($row['branch_code'] ?? '')),
            'account_code' => trim((string) ($row['account_code'] ?? '')),
            'line_description' => trim((string) ($row['line_description'] ?? '')),
            'debit' => (float) trim((string) ($row['debit'] ?? '0')),
            'credit' => (float) trim((string) ($row['credit'] ?? '0')),
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

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
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
            'file' => "Format {$field} tidak valid pada baris {$rowNumber}. Gunakan format YYYY-MM-DD atau DD/MM/YYYY.",
        ]);
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
            if ($candidateHeaders === $expectedHeaders) {
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
