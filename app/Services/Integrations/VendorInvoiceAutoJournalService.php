<?php

namespace App\Services\Integrations;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VendorInvoiceAutoJournalService
{
    public function __construct(private readonly IntegrationEventLifecycleService $lifecycle)
    {
    }

    public function postValidatedEvent(IntegrationEvent $event): array
    {
        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        $preview = data_get($payload, '_posting_preview');

        $this->lifecycle->log($event, 'info', 'Vendor invoice auto posting started.', [
            'event_name' => $event->event_name,
        ]);

        if ($event->source_module !== 'accounts_payable' || $event->event_name !== 'vendor.invoice.posted') {
            return $this->markFailed($event, 'unsupported_vendor_invoice_event');
        }

        if (! is_array($preview) || empty($preview['lines'])) {
            return $this->markFailed($event, 'posting_preview_missing');
        }

        $integrationKey = 'accounts_payable:vendor_invoice:event:' . $event->id;

        $existing = JournalEntry::query()
            ->where('company_id', $event->company_id)
            ->where('integration_key', $integrationKey)
            ->first();

        if ($existing) {
            $this->lifecycle->log($event, 'warning', 'Duplicate auto posting detected. Existing journal reused.', [
                'journal_entry_id' => $existing->id,
            ]);

            return [
                'status' => 'duplicate',
                'journal_entry_id' => $existing->id,
                'error' => null,
            ];
        }

        $entryDate = (string) ($payload['entry_date'] ?? Carbon::parse($event->event_datetime)->toDateString());
        $postingDate = (string) ($payload['posting_date'] ?? $entryDate);
        $referenceNo = isset($payload['reference_no']) ? (string) $payload['reference_no'] : null;
        $branchId = $this->resolveBranchId($event->company_id, data_get($payload, '_meta.branch_id'));

        $period = AccountingPeriod::query()
            ->with('fiscalYear:id,status')
            ->where('company_id', $event->company_id)
            ->whereDate('start_date', '<=', $postingDate)
            ->whereDate('end_date', '>=', $postingDate)
            ->first();

        if (! $period) {
            return $this->markFailed($event, 'accounting_period_not_found');
        }

        if (($period->fiscalYear?->status ?? null) === 'closed' || $period->status !== 'open') {
            return $this->markFailed($event, 'period_not_open');
        }

        $createdBy = User::query()
            ->where('company_id', $event->company_id)
            ->value('id');

        if (! $createdBy) {
            return $this->markFailed($event, 'journal_creator_user_not_found');
        }

        $companyCurrency = Company::query()->whereKey($event->company_id)->value('base_currency_code');
        $currencyCode = (string) ($preview['currency_code'] ?? $payload['currency_code'] ?? $companyCurrency ?? 'IDR');
        $exchangeRate = (float) ($payload['exchange_rate'] ?? 1);

        [$totalDebit, $totalCredit, $lineError] = $this->resolveTotalsAndValidateAccounts($preview['lines'], $event->company_id);

        if ($lineError !== null) {
            return $this->markFailed($event, $lineError);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return $this->markFailed($event, 'unbalanced_journal');
        }

        $journalEntry = DB::transaction(function () use ($event, $period, $entryDate, $postingDate, $createdBy, $currencyCode, $exchangeRate, $preview, $integrationKey, $totalDebit, $totalCredit, $branchId, $referenceNo, $payload) {
            $journalEntry = JournalEntry::create([
                'company_id' => $event->company_id,
                'branch_id' => $branchId,
                'accounting_period_id' => $period->id,
                'journal_no' => $this->generateJournalNumber($event),
                'journal_type' => 'auto',
                'source_module' => $event->source_module,
                'source_event' => $event->event_name,
                'source_document_type' => $event->source_document_type,
                'source_document_id' => $event->source_document_id,
                'source_document_no' => $event->source_document_no,
                'integration_key' => $integrationKey,
                'entry_date' => $entryDate,
                'posting_date' => $postingDate,
                'reference_no' => $referenceNo,
                'description' => (string) ($payload['description'] ?? ('Auto journal from vendor invoice event #' . $event->id)),
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $createdBy,
                'created_by' => $createdBy,
            ]);

            foreach ($preview['lines'] as $line) {
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $isDebit = ($line['line_side'] ?? null) === 'debit';

                JournalLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_no' => (int) ($line['line_no'] ?? 0),
                    'account_id' => (int) $line['account_id'],
                    'description' => $line['description_template'] ?? null,
                    'debit' => $isDebit ? $amount : 0,
                    'credit' => $isDebit ? 0 : $amount,
                    'original_currency_code' => $currencyCode,
                    'original_currency_amount' => $amount,
                    'base_currency_debit' => $isDebit ? round($amount * $exchangeRate, 2) : 0,
                    'base_currency_credit' => $isDebit ? 0 : round($amount * $exchangeRate, 2),
                ]);
            }

            return $journalEntry;
        });

        $event->update([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
            'payload_json' => array_merge($payload, [
                '_journal_entry_id' => $journalEntry->id,
                '_journal_no' => $journalEntry->journal_no,
            ]),
        ]);

        $this->lifecycle->resolveOpenFailures($event);
        $this->lifecycle->log($event, 'info', 'Vendor invoice auto posting completed.', [
            'journal_entry_id' => $journalEntry->id,
            'journal_no' => $journalEntry->journal_no,
        ]);

        return [
            'status' => 'processed',
            'journal_entry_id' => $journalEntry->id,
            'error' => null,
        ];
    }

    private function resolveTotalsAndValidateAccounts(array $lines, int $companyId): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);

            $accountExists = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->exists();

            if (! $accountExists) {
                return [0, 0, 'invalid_or_inactive_account'];
            }

            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($amount <= 0) {
                return [0, 0, 'invalid_line_amount'];
            }

            if (($line['line_side'] ?? null) === 'debit') {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }
        }

        return [round($totalDebit, 2), round($totalCredit, 2), null];
    }

    private function generateJournalNumber(IntegrationEvent $event): string
    {
        $datePart = Carbon::parse($event->event_datetime)->format('Ymd');
        $prefix = 'VI-AUTO-' . $datePart . '-';
        $counter = JournalEntry::query()
            ->where('company_id', $event->company_id)
            ->where('journal_no', 'like', $prefix . '%')
            ->count() + 1;

        return $prefix . str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
    }

    private function markFailed(IntegrationEvent $event, string $error): array
    {
        $event->update([
            'processing_status' => 'failed',
            'processed_at' => now(),
            'error_message' => $error,
        ]);

        $this->lifecycle->recordFailure($event, 'posting', $error, 'Vendor invoice auto posting failed: ' . $error);
        $this->lifecycle->log($event, 'error', 'Vendor invoice auto posting failed.', [
            'error_code' => $error,
        ]);

        return [
            'status' => 'failed',
            'journal_entry_id' => null,
            'error' => $error,
        ];
    }

    private function resolveBranchId(int $companyId, mixed $branchId): ?int
    {
        $resolvedBranchId = is_numeric($branchId) ? (int) $branchId : 0;

        if ($resolvedBranchId <= 0) {
            return null;
        }

        $exists = Branch::query()
            ->where('company_id', $companyId)
            ->whereKey($resolvedBranchId)
            ->exists();

        return $exists ? $resolvedBranchId : null;
    }
}
