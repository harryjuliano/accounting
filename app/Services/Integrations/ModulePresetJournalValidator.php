<?php

namespace App\Services\Integrations;

use App\Models\ChartOfAccount;
use App\Models\IntegrationEvent;

class ModulePresetJournalValidator
{
    public function __construct(private readonly IntegrationEventLifecycleService $lifecycle)
    {
    }

    public function validateAndMark(IntegrationEvent $event): array
    {
        $payload = is_array($event->payload_json) ? $event->payload_json : [];

        $this->lifecycle->log($event, 'info', 'Module preset journal validation started.', [
            'source_module' => $event->source_module,
            'event_name' => $event->event_name,
        ]);

        if (PostingMode::fromPayload($payload) !== PostingMode::MODULE_PRESET) {
            return $this->markFailed($event, 'invalid_posting_mode');
        }

        [$preview, $error] = $this->buildPreview($event, $payload);

        if ($error !== null) {
            return $this->markFailed($event, $error);
        }

        $event->update([
            'payload_json' => array_merge($payload, [
                '_posting_mode' => PostingMode::MODULE_PRESET,
                '_posting_preview' => $preview,
            ]),
            'processing_status' => 'validated',
            'processed_at' => now(),
            'error_message' => null,
        ]);

        $this->lifecycle->resolveOpenFailures($event);
        $this->lifecycle->log($event, 'info', 'Module preset journal validation completed.', [
            'total_debit' => $preview['total_debit'],
            'total_credit' => $preview['total_credit'],
            'line_count' => count($preview['lines']),
        ]);

        return [
            'status' => 'validated',
            'error' => null,
        ];
    }

    private function buildPreview(IntegrationEvent $event, array $payload): array
    {
        $journal = $payload['journal'] ?? null;

        if (! is_array($journal)) {
            return [[], 'journal_payload_missing'];
        }

        $incomingLines = $journal['lines'] ?? null;

        if (! is_array($incomingLines) || count($incomingLines) < 2) {
            return [[], 'journal_lines_missing'];
        }

        $lines = [];
        $lineNumbers = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($incomingLines as $index => $line) {
            if (! is_array($line)) {
                return [[], 'invalid_journal_line'];
            }

            $lineNo = (int) ($line['line_no'] ?? ($index + 1));

            if ($lineNo < 1 || in_array($lineNo, $lineNumbers, true)) {
                return [[], 'invalid_or_duplicate_line_no'];
            }

            $lineNumbers[] = $lineNo;

            $lineSide = (string) ($line['line_side'] ?? '');

            if (! in_array($lineSide, ['debit', 'credit'], true)) {
                return [[], 'invalid_line_side'];
            }

            $account = $this->resolveAccount($event->company_id, $line);

            if (! $account) {
                return [[], 'invalid_account_reference'];
            }

            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($amount <= 0) {
                return [[], 'invalid_line_amount'];
            }

            if ($lineSide === 'debit') {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }

            $lines[] = [
                'line_no' => $lineNo,
                'line_side' => $lineSide,
                'account_id' => $account->id,
                'account_code' => $account->code,
                'amount' => $amount,
                'description_template' => $line['description'] ?? $line['description_template'] ?? null,
                'item_code' => $line['item_code'] ?? null,
                'item_name' => $line['item_name'] ?? null,
                'quantity' => isset($line['quantity']) && $line['quantity'] !== '' ? (float) $line['quantity'] : null,
                'quantity_uom' => $line['quantity_uom'] ?? null,
                'dimension_details' => $line['dimensions'] ?? $line['dimension_details'] ?? null,
            ];
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return [[], 'unbalanced_journal'];
        }

        return [[
            'posting_mode' => PostingMode::MODULE_PRESET,
            'currency_code' => $payload['currency_code'] ?? null,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'lines' => $lines,
        ], null];
    }

    private function resolveAccount(int $companyId, array $line): ?ChartOfAccount
    {
        $query = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true);

        if (filled($line['account_id'] ?? null)) {
            $query->where('id', (int) $line['account_id']);
        } elseif (filled($line['account_code'] ?? null)) {
            $query->where('code', (string) $line['account_code']);
        } else {
            return null;
        }

        $account = $query->first();

        if (! $account || ! $account->allow_manual_posting) {
            return null;
        }

        if (filled($line['account_id'] ?? null) && filled($line['account_code'] ?? null) && $account->code !== (string) $line['account_code']) {
            return null;
        }

        return $account;
    }

    private function markFailed(IntegrationEvent $event, string $error): array
    {
        $event->update([
            'processing_status' => 'failed',
            'processed_at' => now(),
            'error_message' => $error,
        ]);

        $this->lifecycle->recordFailure($event, 'validation', $error, 'Module preset journal validation failed: ' . $error);
        $this->lifecycle->log($event, 'error', 'Module preset journal validation failed.', [
            'error_code' => $error,
        ]);

        return [
            'status' => 'failed',
            'error' => $error,
        ];
    }
}
