<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integrations\UniversalJournalRequest;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Integrations\IntegrationClientCredentialService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UniversalJournalController extends Controller
{
    public function __construct(private readonly IntegrationClientCredentialService $credentialService)
    {
    }

    public function store(UniversalJournalRequest $request): JsonResponse
    {
        $this->ensureValidIntegrationToken($request->header('X-Integration-Token'));

        $validated = $request->validated();
        $sourceModule = Str::lower((string) $validated['source_module']);

        $credential = $this->credentialService->resolve(
            $sourceModule,
            (string) $validated['client_key'],
            (string) $validated['client_secret'],
        );

        abort_unless(
            $credential,
            401,
            'Invalid client credential for universal journal posting. Use a client_key/client_secret generated with --module=' . $sourceModule . ' or --module=all.'
        );

        $company = Company::query()->findOrFail($credential->company_id);

        if (filled($validated['company_code'] ?? null) && $company->code !== (string) $validated['company_code']) {
            throw ValidationException::withMessages([
                'company_code' => 'company_code tidak sesuai dengan company pada credential integrasi.',
            ]);
        }

        $branchId = $this->resolveBranchId($company->id, $credential->branch_id, $validated['branch_code'] ?? null);
        $period = $this->resolveAccountingPeriod($company->id, (string) $validated['posting_date']);
        $createdBy = $this->resolveCreatedBy($company->id);

        $existing = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('integration_key', $validated['integration_key'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Duplicate universal journal received. Existing journal reused.',
                'data' => [
                    'journal_entry_id' => $existing->id,
                    'journal_no' => $existing->journal_no,
                    'integration_key' => $existing->integration_key,
                    'is_duplicate' => true,
                    'company_id' => $company->id,
                    'branch_id' => $existing->branch_id,
                ],
            ]);
        }

        $lines = $this->buildLines($validated['lines'], $company->id, (string) $validated['currency_code'], (float) $validated['exchange_rate']);
        $totalDebit = round(collect($lines)->sum('debit'), 2);
        $totalCredit = round(collect($lines)->sum('credit'), 2);

        if ($totalDebit <= 0 || $totalCredit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit dan kredit harus seimbang serta lebih dari 0.',
            ]);
        }

        $journalNo = $this->resolveJournalNo($company->id, $sourceModule, $validated['posting_date'], $validated['journal_no'] ?? null);

        $journalEntry = DB::transaction(function () use ($validated, $company, $branchId, $period, $createdBy, $lines, $totalDebit, $totalCredit, $sourceModule, $journalNo) {
            $journalEntry = JournalEntry::create([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'accounting_period_id' => $period->id,
                'journal_no' => $journalNo,
                'journal_type' => $validated['journal_type'],
                'source_module' => $sourceModule,
                'source_module_name' => $validated['source_module_name'] ?? null,
                'source_event' => $validated['source_event'] ?? 'universal_journal_posted',
                'source_document_type' => $validated['source_document_type'] ?? null,
                'source_document_id' => $validated['source_document_id'] ?? null,
                'source_document_no' => $validated['source_document_no'] ?? null,
                'integration_key' => $validated['integration_key'],
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
                'posted_at' => $validated['status'] === 'posted' ? now() : null,
                'posted_by' => $validated['status'] === 'posted' ? $createdBy : null,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $journalEntry->lines()->create($line);
            }

            return $journalEntry;
        });

        return response()->json([
            'message' => 'Universal journal posted.',
            'data' => [
                'journal_entry_id' => $journalEntry->id,
                'journal_no' => $journalEntry->journal_no,
                'integration_key' => $journalEntry->integration_key,
                'is_duplicate' => false,
                'company_id' => $company->id,
                'branch_id' => $journalEntry->branch_id,
                'total_debit' => (float) $journalEntry->total_debit,
                'total_credit' => (float) $journalEntry->total_credit,
            ],
        ], 201);
    }

    private function resolveBranchId(int $companyId, ?int $credentialBranchId, ?string $branchCode): ?int
    {
        if (filled($branchCode)) {
            $branchId = Branch::query()
                ->where('company_id', $companyId)
                ->where('code', $branchCode)
                ->value('id');

            if (! $branchId) {
                throw ValidationException::withMessages([
                    'branch_code' => "Branch code {$branchCode} tidak ditemukan.",
                ]);
            }

            return (int) $branchId;
        }

        return $credentialBranchId;
    }

    private function resolveAccountingPeriod(int $companyId, string $postingDate): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->with('fiscalYear:id,status')
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $postingDate)
            ->whereDate('end_date', '>=', $postingDate)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
            ]);
        }

        if (($period->fiscalYear?->status ?? null) === 'closed' || $period->status !== 'open') {
            throw ValidationException::withMessages([
                'posting_date' => 'Periode fiskal tidak open sehingga jurnal tidak dapat diposting.',
            ]);
        }

        return $period;
    }

    private function resolveCreatedBy(int $companyId): int
    {
        $createdBy = User::query()
            ->where('company_id', $companyId)
            ->value('id');

        if (! $createdBy) {
            throw ValidationException::withMessages([
                'created_by' => 'User pembuat jurnal untuk company ini tidak ditemukan.',
            ]);
        }

        return (int) $createdBy;
    }

    private function buildLines(array $incomingLines, int $companyId, string $currencyCode, float $exchangeRate): array
    {
        $lineNumbers = [];
        $lines = [];

        foreach ($incomingLines as $index => $line) {
            $lineNo = (int) ($line['line_no'] ?? ($index + 1));

            if ($lineNo < 1 || in_array($lineNo, $lineNumbers, true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.line_no" => 'line_no harus unik dan lebih dari 0.',
                ]);
            }

            $lineNumbers[] = $lineNo;
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.debit" => 'Setiap baris harus berisi debit atau kredit saja.',
                ]);
            }

            $account = $this->resolveAccount($companyId, $line, $index);
            $originalAmount = $debit > 0 ? $debit : $credit;

            $lines[] = [
                'line_no' => $lineNo,
                'account_id' => $account->id,
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
                'dimension_details_json' => $this->buildDimensionDetails($line),
            ];
        }

        return $lines;
    }

    private function resolveAccount(int $companyId, array $line, int $index): ChartOfAccount
    {
        $query = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true);

        if (filled($line['account_id'] ?? null)) {
            $query->where('id', (int) $line['account_id']);
        } elseif (filled($line['account_code'] ?? null)) {
            $query->where('code', (string) $line['account_code']);
        } else {
            throw ValidationException::withMessages([
                "lines.{$index}.account_code" => 'account_code atau account_id wajib diisi.',
            ]);
        }

        $account = $query->first();

        if (! $account) {
            throw ValidationException::withMessages([
                "lines.{$index}.account_code" => 'Akun pada baris jurnal tidak ditemukan.',
            ]);
        }

        if (filled($line['account_id'] ?? null) && filled($line['account_code'] ?? null) && $account->code !== (string) $line['account_code']) {
            throw ValidationException::withMessages([
                "lines.{$index}.account_code" => 'account_id dan account_code tidak mengarah ke akun yang sama.',
            ]);
        }

        return $account;
    }

    private function buildDimensionDetails(array $line): ?array
    {
        $dimensions = $line['dimensions'] ?? $line['dimension_details'] ?? [];
        $dimensions = is_array($dimensions) ? $dimensions : [];

        if (filled($line['cost_center_code'] ?? null) || filled($line['cost_center_name'] ?? null)) {
            $dimensions['cost_center'] = [
                'code' => $line['cost_center_code'] ?? null,
                'name' => $line['cost_center_name'] ?? null,
            ];
        }

        return $dimensions === [] ? null : $dimensions;
    }

    private function resolveJournalNo(int $companyId, string $sourceModule, string $postingDate, ?string $journalNo): string
    {
        if (filled($journalNo)) {
            $exists = JournalEntry::query()
                ->where('company_id', $companyId)
                ->where('journal_no', $journalNo)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'journal_no' => "Nomor jurnal {$journalNo} sudah terdaftar. Gunakan nomor jurnal lain.",
                ]);
            }

            return $journalNo;
        }

        $prefix = strtoupper(Str::slug($sourceModule, '-')) . '-UNI-' . Carbon::parse($postingDate)->format('Ymd') . '-';
        $nextNumber = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('journal_no', 'like', $prefix . '%')
            ->count() + 1;

        do {
            $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (JournalEntry::query()->where('company_id', $companyId)->where('journal_no', $candidate)->exists());

        return $candidate;
    }

    private function ensureValidIntegrationToken(?string $incomingToken): void
    {
        $expectedToken = config('services.integration.generic_token');

        if (! filled($expectedToken)) {
            return;
        }

        abort_unless(hash_equals((string) $expectedToken, (string) $incomingToken), 401, 'Invalid integration token.');
    }
}
