<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningBalanceRequest;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceCrudController extends Controller
{
    use InteractsWithCompanyScope;

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

        $manualJournals = JournalEntry::query()
            ->with(['company:id,name,timezone', 'branch:id,company_id,code,name', 'accountingPeriod:id,period_name', 'lines.account:id,company_id,code,name,requires_dimension'])
            ->where('journal_type', 'opening')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->whereYear('posting_date', $year)
            ->when(! $isAllMonths, fn ($query) => $query->whereMonth('posting_date', $month))
            ->when($branchId !== 'all', fn ($query) => $query->where('branch_id', $branchId))
            ->when($statusFilter !== 'all', fn ($query) => $query->where('status', $statusFilter))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('journal_no', 'like', '%' . $request->search . '%')
                        ->orWhere('reference_no', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%')
                        ->orWhere('entry_date', 'like', '%' . $request->search . '%')
                        ->orWhere('posting_date', 'like', '%' . $request->search . '%')
                        ->orWhere('currency_code', 'like', '%' . $request->search . '%')
                        ->orWhere('status', 'like', '%' . $request->search . '%');
                });
            })
            ->latest('posting_date')
            ->paginate(10)
            ->withQueryString();

        $years = JournalEntry::query()
            ->selectRaw('DISTINCT YEAR(posting_date) as year')
            ->where('journal_type', 'opening')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->values();

        if (! $years->contains($year)) {
            $years->prepend($year);
        }

        return inertia('Apps/OpeningBalances/Index', [
            'manualJournals' => $manualJournals,
            'deepLinkJournal' => null,
            'companies' => $this->getAccessibleCompanies(),
            'branches' => Branch::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'accountingPeriods' => AccountingPeriod::query()->select('id', 'company_id', 'period_name', 'period_no', 'start_date', 'end_date', 'status', 'fiscal_year_id')
                ->with('fiscalYear:id,start_date,status')
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
                'by' => 'posting_date',
                'direction' => 'desc',
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

    public function store(OpeningBalanceRequest $request)
    {
        $this->saveOpeningEntry($request->validated(), $request);

        return back();
    }

    public function bulkPost(Request $request)
    {
        $validated = $request->validate([
            'journal_ids' => ['required', 'array', 'min:1'],
            'journal_ids.*' => ['integer', 'exists:journal_entries,id'],
        ]);

        $entries = JournalEntry::query()
            ->where('journal_type', 'opening')
            ->whereIn('id', $validated['journal_ids'])
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->get();

        DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                $accountingPeriod = $this->resolveOpeningAccountingPeriod((int) $entry->company_id, (string) $entry->posting_date);
                $this->ensurePeriodAllowsOpening($accountingPeriod, (string) $entry->posting_date);
                $entry->update(['status' => 'posted']);
            }
        });

        return back();
    }

    public function update(OpeningBalanceRequest $request, JournalEntry $opening_balance)
    {
        $this->enforceCompanyAccess((int) $opening_balance->company_id);

        if ($opening_balance->journal_type !== 'opening') {
            abort(404);
        }

        $this->saveOpeningEntry($request->validated(), $request, $opening_balance);

        return back();
    }

    public function destroy(JournalEntry $opening_balance)
    {
        $this->enforceCompanyAccess((int) $opening_balance->company_id);

        if ($opening_balance->journal_type !== 'opening') {
            abort(404);
        }

        $opening_balance->delete();

        return back();
    }

    public function importFromCsv(Request $request)
    {
        $maxUploadKb = max(1, (int) config('imports.opening_balances.max_upload_kb', 20 * 1024));
        $maxRows = max(2, (int) config('imports.opening_balances.max_rows', 50000));

        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxUploadKb}"],
        ]);
        $companyId = $this->resolveLoggedInCompanyId($request);

        $rows = $this->parseOpeningBalanceCsv($payload['file']);

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

        DB::transaction(function () use ($groupedRows, $request, $companyId): void {
            foreach ($groupedRows as $journalRows) {
                $firstRow = $journalRows->first();
                $journalNo = $firstRow['journal_no'];

                $this->validateImportHeaderConsistency($journalRows->all(), $journalNo, $firstRow);

                $existingEntry = JournalEntry::query()
                    ->where('company_id', $companyId)
                    ->where('journal_no', $journalNo)
                    ->exists();

                if ($existingEntry) {
                    throw ValidationException::withMessages([
                        'file' => "Nomor jurnal {$journalNo} sudah terdaftar. Gunakan nomor jurnal lain.",
                    ]);
                }

                $accountingPeriod = $this->resolveOpeningAccountingPeriod($companyId, $firstRow['posting_date']);
                $this->ensurePeriodAllowsOpening($accountingPeriod, $firstRow['posting_date']);

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
                    'journal_type' => 'opening',
                    'source_module' => 'opening_balance_import',
                    'source_event' => 'csv_import',
                    'source_document_type' => 'opening_balance_template',
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
            }
        });

        return back()->with('success', 'Import saldo awal berhasil diproses.');
    }

    public function downloadImportTemplate(Request $request)
    {
        $headers = $this->openingBalanceTemplateHeaders();
        $this->resolveLoggedInCompanyId($request);

        $sampleRows = [
            ['OB-2026-0001', '2026-01-01', '2026-01-01', 'SALDO-AWAL', 'Saldo awal FY 2026', 'IDR', '1', 'posted', 'JKT', '111001', 'Kas awal', '1000000', '0'],
            ['OB-2026-0001', '2026-01-01', '2026-01-01', 'SALDO-AWAL', 'Saldo awal FY 2026', 'IDR', '1', 'posted', 'JKT', '310001', 'Ekuitas awal', '0', '1000000'],
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
            'Content-Disposition' => 'attachment; filename="opening-balance-import-template.csv"',
        ]);
    }

    private function saveOpeningEntry(array $validated, Request $request, ?JournalEntry $journalEntry = null): void
    {
        DB::transaction(function () use ($validated, $request, $journalEntry): void {
            $accountingPeriod = $this->resolveOpeningAccountingPeriod((int) $validated['company_id'], (string) $validated['posting_date']);
            $this->ensurePeriodAllowsOpening($accountingPeriod, (string) $validated['posting_date']);

            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            if (! $journalEntry) {
                $journalEntry = new JournalEntry();
                $journalEntry->journal_type = 'opening';
                $journalEntry->source_module = 'opening_balance_manual';
                $journalEntry->source_event = 'manual_input';
                $journalEntry->source_document_type = 'opening_balance';
                $journalEntry->created_by = $request->user()->id;
            }

            $journalEntry->fill([
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
            $journalEntry->save();

            $journalEntry->lines()->delete();

            foreach ($validated['lines'] as $index => $line) {
                $journalEntry->lines()->create($this->buildJournalLinePayload(
                    $line,
                    $index + 1,
                    (string) $validated['currency_code'],
                    (float) $validated['exchange_rate']
                ));
            }
        });
    }

    private function resolveOpeningAccountingPeriod(int $companyId, string $postingDate): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->with('fiscalYear:id,start_date,status')
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $postingDate)
            ->whereDate('end_date', '>=', $postingDate)
            ->first();
    }

    private function ensurePeriodAllowsOpening(?AccountingPeriod $accountingPeriod, string $postingDate): void
    {
        if (! $accountingPeriod) {
            throw ValidationException::withMessages([
                'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
            ]);
        }

        if ((int) $accountingPeriod->period_no !== 1) {
            throw ValidationException::withMessages([
                'posting_date' => 'Saldo awal hanya boleh diposting pada periode Januari (periode 1).',
            ]);
        }

        if ($accountingPeriod->fiscalYear?->status === 'closed' || $accountingPeriod->status !== 'open') {
            throw ValidationException::withMessages([
                'posting_date' => 'Periode/fiscal year sudah ditutup. Input saldo awal tidak diizinkan.',
            ]);
        }

        if ((string) $postingDate !== (string) optional($accountingPeriod->fiscalYear?->start_date)->toDateString()) {
            throw ValidationException::withMessages([
                'posting_date' => 'Tanggal posting saldo awal harus sama dengan tanggal awal tahun fiskal.',
            ]);
        }
    }

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

    private function openingBalanceTemplateHeaders(): array
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

    private function parseOpeningBalanceCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $expectedHeaders = $this->openingBalanceTemplateHeaders();
        $delimiter = $this->detectCsvDelimiter($handle, $expectedHeaders);
        $headers = $this->normalizeCsvHeaders(fgetcsv($handle, 0, $delimiter) ?: []);

        if ($headers !== $expectedHeaders) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'Format file tidak sesuai template import saldo awal.',
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
            $rows[] = $this->normalizeOpeningBalanceRow($row, $rowNumber);
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeOpeningBalanceRow(array $row, int $rowNumber): array
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
            'account_code' => trim((string) ($row['account_code'] ?? '')),
            'line_description' => trim((string) ($row['line_description'] ?? '')),
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

    private function validateImportHeaderConsistency(array $journalRows, string $journalNo, array $firstRow): void
    {
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
        $companyId = $request->user()?->company_id;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'file' => 'User tidak terhubung ke company. Tidak dapat memproses import.',
            ]);
        }

        return (int) $companyId;
    }

    private function normalizeCsvHeaders(array $headers): array
    {
        return collect($headers)
            ->map(fn ($header) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header))))
            ->values()
            ->all();
    }

    private function detectCsvDelimiter($handle, array $expectedHeaders): string
    {
        $startPosition = ftell($handle);
        $line = fgets($handle);
        fseek($handle, $startPosition);

        if ($line === false) {
            return ',';
        }

        $candidateDelimiters = [',', ';', "\t", '|'];

        foreach ($candidateDelimiters as $delimiter) {
            $parsed = str_getcsv($line, $delimiter);
            $normalized = $this->normalizeCsvHeaders($parsed);

            if ($normalized === $expectedHeaders) {
                return $delimiter;
            }
        }

        $commaCount = substr_count($line, ',');
        $semicolonCount = substr_count($line, ';');

        if ($semicolonCount > $commaCount) {
            return ';';
        }

        return ',';
    }
}
