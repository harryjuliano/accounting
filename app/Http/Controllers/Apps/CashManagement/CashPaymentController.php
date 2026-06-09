<?php

namespace App\Http\Controllers\Apps\CashManagement;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\CashManagementAccount;
use App\Models\CashTransaction;
use App\Models\ChartOfAccount;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashPaymentController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request)
    {
        $payments = CashTransaction::query()
            ->with([
                'company:id,name,base_currency_code,timezone',
                'cashAccount:id,company_id,account_code,account_name,account_type,gl_account_id,currency_code',
                'paymentLines.debitAccount:id,code,name',
                'journalEntry:id,journal_no,status',
            ])
            ->where('transaction_type', 'payment')
            ->where('direction', 'out')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('document_no', 'like', '%' . $request->search . '%')
                        ->orWhere('counterparty_name', 'like', '%' . $request->search . '%')
                        ->orWhere('reference_no', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%')
                        ->orWhereHas('cashAccount', fn ($accountQuery) => $accountQuery
                            ->where('account_code', 'like', '%' . $request->search . '%')
                            ->orWhere('account_name', 'like', '%' . $request->search . '%'));
                });
            })
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $companies = $this->getAccessibleCompanies();
        $companyIds = $companies->pluck('id');

        return inertia('Apps/CashManagement/CashPayments/Index', [
            'payments' => $payments,
            'companies' => $companies,
            'cashAccounts' => CashManagementAccount::query()
                ->with('glAccount:id,code,name')
                ->whereIn('company_id', $companyIds)
                ->whereIn('account_type', ['cash', 'bank'])
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get(['id', 'company_id', 'account_code', 'account_name', 'account_type', 'currency_code', 'gl_account_id']),
            'chartOfAccounts' => ChartOfAccount::query()
                ->whereIn('company_id', $companyIds)
                ->where('is_active', true)
                ->where('allow_manual_posting', true)
                ->orderBy('code')
                ->get(['id', 'company_id', 'code', 'name', 'account_type']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request) {
            $payment = CashTransaction::create($this->paymentPayload($data, $request));
            $this->syncLines($payment, $data['lines']);
            $this->generateIntegrationJournal($payment->fresh(['paymentLines.debitAccount', 'cashAccount.glAccount']), $request);
        });

        return back();
    }

    public function update(Request $request, CashTransaction $cash_payment)
    {
        $this->ensurePayment($cash_payment);
        $this->enforceCompanyAccess((int) $cash_payment->company_id);

        $data = $this->validatedData($request, $cash_payment->id);

        DB::transaction(function () use ($cash_payment, $data, $request) {
            $cash_payment->journalEntry?->lines()->delete();
            $cash_payment->journalEntry?->delete();
            $cash_payment->integrationEvent?->delete();
            $cash_payment->update(array_merge($this->paymentPayload($data, $request), [
                'journal_entry_id' => null,
                'integration_event_id' => null,
            ]));
            $this->syncLines($cash_payment, $data['lines']);
            $this->generateIntegrationJournal($cash_payment->fresh(['paymentLines.debitAccount', 'cashAccount.glAccount']), $request);
        });

        return back();
    }

    public function destroy(CashTransaction $cash_payment)
    {
        $this->ensurePayment($cash_payment);
        $this->enforceCompanyAccess((int) $cash_payment->company_id);

        DB::transaction(function () use ($cash_payment) {
            $cash_payment->journalEntry?->delete();
            $cash_payment->integrationEvent?->delete();
            $cash_payment->delete();
        });

        return back();
    }

    public function voucher(Request $request, CashTransaction $cash_payment)
    {
        $this->ensurePayment($cash_payment);
        $this->enforceCompanyAccess((int) $cash_payment->company_id);

        $cash_payment->load(['company', 'cashAccount.glAccount', 'paymentLines.debitAccount', 'journalEntry']);

        return response()
            ->view('cash-management.cash-payments.voucher', ['payment' => $cash_payment])
            ->header('Content-Type', 'text/html');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $companyIds = $this->getAccessibleCompanies()->pluck('id')->all();
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::in($companyIds)],
            'cash_management_account_id' => ['required', 'integer', Rule::exists('cash_management_accounts', 'id')->where(fn ($query) => $query->whereIn('account_type', ['cash', 'bank'])->where('is_active', true))],
            'document_no' => ['nullable', 'string', 'max:255', Rule::unique('cash_transactions', 'document_no')->where('company_id', $request->integer('company_id'))->ignore($ignoreId)],
            'transaction_date' => ['required', 'date'],
            'posting_date' => ['nullable', 'date'],
            'counterparty_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.debit_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.transaction_code' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        $cashAccount = CashManagementAccount::query()->with('glAccount')->findOrFail($data['cash_management_account_id']);
        if ((int) $cashAccount->company_id !== (int) $data['company_id']) {
            throw ValidationException::withMessages(['cash_management_account_id' => 'Akun kas/bank harus berada pada company yang sama.']);
        }
        if (! $cashAccount->gl_account_id) {
            throw ValidationException::withMessages(['cash_management_account_id' => 'Akun kas/bank wajib memiliki mapping COA untuk sisi kredit jurnal.']);
        }

        $accountCompanyIds = ChartOfAccount::query()->whereIn('id', collect($data['lines'])->pluck('debit_account_id'))->pluck('company_id', 'id');
        foreach ($data['lines'] as $index => $line) {
            if ((int) ($accountCompanyIds[$line['debit_account_id']] ?? 0) !== (int) $data['company_id']) {
                throw ValidationException::withMessages(["lines.$index.debit_account_id" => 'COA debit harus berada pada company yang sama.']);
            }
        }

        $data['amount'] = collect($data['lines'])->sum(fn ($line) => (float) $line['amount']);
        $data['currency_code'] = $cashAccount->currency_code;
        $data['posting_date'] = $data['posting_date'] ?: $data['transaction_date'];

        return $data;
    }

    private function paymentPayload(array $data, Request $request): array
    {
        return [
            'company_id' => $data['company_id'],
            'document_no' => $data['document_no'] ?: $this->nextDocumentNo($data['company_id']),
            'transaction_type' => 'payment',
            'direction' => 'out',
            'cash_management_account_id' => $data['cash_management_account_id'],
            'counterparty_type' => 'other',
            'counterparty_name' => $data['counterparty_name'],
            'transaction_date' => $data['transaction_date'],
            'posting_date' => $data['posting_date'],
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'],
            'exchange_rate' => 1,
            'status' => 'posted',
            'payment_method' => $data['payment_method'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'description' => $data['description'],
            'narration' => $data['description'],
            'created_by' => $request->user()->id,
            'posted_by' => $request->user()->id,
            'posted_at' => now(),
        ];
    }

    private function syncLines(CashTransaction $payment, array $lines): void
    {
        $payment->paymentLines()->delete();
        foreach (array_values($lines) as $index => $line) {
            $payment->paymentLines()->create([
                'line_no' => $index + 1,
                'debit_account_id' => $line['debit_account_id'],
                'transaction_code' => $line['transaction_code'] ?? null,
                'description' => $line['description'] ?? null,
                'amount' => $line['amount'],
                'reference_no' => $line['reference_no'] ?? null,
            ]);
        }
    }

    private function generateIntegrationJournal(CashTransaction $payment, Request $request): void
    {
        $period = $this->resolveAccountingPeriod((int) $payment->company_id, $payment->posting_date->toDateString());
        if (! $period) {
            throw ValidationException::withMessages(['posting_date' => 'Periode akuntansi untuk tanggal posting tidak ditemukan.']);
        }
        if ($period->status !== 'open' || $period->fiscalYear?->status === 'closed') {
            throw ValidationException::withMessages(['posting_date' => 'Periode akuntansi sudah ditutup.']);
        }

        if ($payment->integrationEvent) {
            $payment->integrationEvent->delete();
        }

        $event = IntegrationEvent::create([
            'company_id' => $payment->company_id,
            'source_module' => 'cash_management',
            'event_name' => 'cash.payment.posted',
            'source_document_type' => 'cash_payment',
            'source_document_id' => (string) $payment->id,
            'source_document_no' => $payment->document_no,
            'idempotency_key' => 'cash-payment-'.$payment->company_id.'-'.$payment->document_no,
            'payload_json' => [
                'payment_id' => $payment->id,
                'document_no' => $payment->document_no,
                'cash_account_id' => $payment->cash_management_account_id,
                'credit_account_id' => $payment->cashAccount->gl_account_id,
                'debit_lines' => $payment->paymentLines->map(fn ($line) => [
                    'account_id' => $line->debit_account_id,
                    'amount' => (float) $line->amount,
                    'description' => $line->description,
                    'reference_no' => $line->reference_no,
                ])->all(),
            ],
            'event_datetime' => now(),
            'processing_status' => 'processed',
            'processed_at' => now(),
        ]);

        $journal = JournalEntry::create([
            'company_id' => $payment->company_id,
            'accounting_period_id' => $period->id,
            'journal_no' => $this->nextJournalNo($payment->company_id, $payment->document_no),
            'journal_type' => 'auto',
            'source_module' => 'cash_management',
            'source_event' => 'cash.payment.posted',
            'source_document_type' => 'cash_payment',
            'source_document_id' => (string) $payment->id,
            'source_document_no' => $payment->document_no,
            'integration_key' => 'cash-payment-journal-'.$payment->company_id.'-'.$payment->document_no,
            'entry_date' => $payment->transaction_date,
            'posting_date' => $payment->posting_date,
            'reference_no' => $payment->reference_no,
            'description' => $payment->description,
            'currency_code' => $payment->currency_code,
            'exchange_rate' => $payment->exchange_rate,
            'total_debit' => $payment->amount,
            'total_credit' => $payment->amount,
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        foreach ($payment->paymentLines as $index => $line) {
            $journal->lines()->create([
                'line_no' => $index + 1,
                'account_id' => $line->debit_account_id,
                'description' => $line->description ?: $payment->description,
                'debit' => $line->amount,
                'credit' => 0,
                'original_currency_code' => $payment->currency_code,
                'original_currency_amount' => $line->amount,
                'base_currency_debit' => $line->amount,
                'base_currency_credit' => 0,
            ]);
        }

        $journal->lines()->create([
            'line_no' => $payment->paymentLines->count() + 1,
            'account_id' => $payment->cashAccount->gl_account_id,
            'description' => 'Kas/Bank keluar '.$payment->document_no,
            'debit' => 0,
            'credit' => $payment->amount,
            'original_currency_code' => $payment->currency_code,
            'original_currency_amount' => $payment->amount,
            'base_currency_debit' => 0,
            'base_currency_credit' => $payment->amount,
        ]);

        $payment->update(['integration_event_id' => $event->id, 'journal_entry_id' => $journal->id]);
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

    private function nextJournalNo(int $companyId, string $documentNo): string
    {
        $base = 'JRN-'.$documentNo;
        $journalNo = $base;
        $sequence = 1;

        while (JournalEntry::query()->where('company_id', $companyId)->where('journal_no', $journalNo)->exists()) {
            $sequence++;
            $journalNo = $base.'-'.$sequence;
        }

        return $journalNo;
    }

    private function nextDocumentNo(int $companyId): string
    {
        $prefix = 'CBPV'.now()->format('ym');
        $last = CashTransaction::query()
            ->where('company_id', $companyId)
            ->where('document_no', 'like', $prefix.'%')
            ->orderByDesc('document_no')
            ->value('document_no');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function ensurePayment(CashTransaction $payment): void
    {
        abort_unless($payment->transaction_type === 'payment' && $payment->direction === 'out', 404);
    }
}
