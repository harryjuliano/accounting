<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;
use App\Models\IntegrationOutbox;
use App\Models\JournalEntry;
use Illuminate\Support\Arr;

class FinanceHubInventoryOutboxService
{
    public const DESTINATION_SYSTEM = 'finance_hub';
    public const EVENT_NAME = 'finance_hub.inventory_out.posted';

    public function enqueueInventoryOutPosted(IntegrationEvent $event, JournalEntry $journalEntry, array $payload): ?IntegrationOutbox
    {
        if (! $this->isInventoryOutEvent($event, $payload)) {
            return null;
        }

        $outboxPayload = $this->buildPayload($event, $journalEntry, $payload);

        return IntegrationOutbox::query()->firstOrCreate(
            [
                'destination_system' => self::DESTINATION_SYSTEM,
                'idempotency_key' => 'finance_hub:inventory_out:event:' . $event->id,
            ],
            [
                'company_id' => $event->company_id,
                'integration_event_id' => $event->id,
                'journal_entry_id' => $journalEntry->id,
                'source_module' => 'inventory',
                'event_name' => self::EVENT_NAME,
                'payload_json' => $outboxPayload,
                'status' => 'pending',
                'retry_count' => 0,
                'available_at' => now(),
            ]
        );
    }

    private function isInventoryOutEvent(IntegrationEvent $event, array $payload): bool
    {
        $transactionType = (string) ($payload['transaction_type'] ?? $event->event_name);

        return str_starts_with($transactionType, 'inventory.issue.')
            || str_starts_with($transactionType, 'inventory.cogs.')
            || $event->event_name === 'inventory.cogs.posted';
    }

    private function buildPayload(IntegrationEvent $event, JournalEntry $journalEntry, array $payload): array
    {
        $journalEntry->loadMissing('lines.account');

        return [
            'event_name' => self::EVENT_NAME,
            'source_module' => 'inventory',
            'source_event_name' => $event->event_name,
            'transaction_type' => (string) ($payload['transaction_type'] ?? $event->event_name),
            'integration_event_id' => $event->id,
            'idempotency_key' => 'finance_hub:inventory_out:event:' . $event->id,
            'source_document' => [
                'type' => $event->source_document_type,
                'id' => $event->source_document_id,
                'no' => $event->source_document_no,
                'reference_no' => $journalEntry->reference_no,
            ],
            'journal' => [
                'id' => $journalEntry->id,
                'journal_no' => $journalEntry->journal_no,
                'posting_date' => $journalEntry->posting_date?->toDateString(),
                'entry_date' => $journalEntry->entry_date?->toDateString(),
                'currency_code' => $journalEntry->currency_code,
                'exchange_rate' => (float) $journalEntry->exchange_rate,
                'total_debit' => (float) $journalEntry->total_debit,
                'total_credit' => (float) $journalEntry->total_credit,
                'description' => $journalEntry->description,
                'lines' => $journalEntry->lines->map(fn ($line) => [
                    'line_no' => $line->line_no,
                    'account_id' => $line->account_id,
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'description' => $line->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ])->values()->all(),
            ],
            'inventory_payload' => Arr::except($payload, ['_posting_preview']),
            'created_at' => now()->toJSON(),
        ];
    }
}
