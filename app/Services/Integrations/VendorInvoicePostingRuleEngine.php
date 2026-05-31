<?php

namespace App\Services\Integrations;

use App\Models\ChartOfAccount;
use App\Models\IntegrationEvent;
use App\Models\PostingRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VendorInvoicePostingRuleEngine
{
    public function __construct(private readonly IntegrationEventLifecycleService $lifecycle)
    {
    }

    public function validateAndMark(IntegrationEvent $event): array
    {
        $eventDate = Carbon::parse($event->event_datetime)->toDateString();
        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        $transactionType = (string) ($payload['transaction_type'] ?? $event->event_name);

        $this->lifecycle->log($event, 'info', 'Vendor invoice validation started.', [
            'event_name' => $event->event_name,
            'transaction_type' => $transactionType,
        ]);

        if ($event->source_module !== 'accounts_payable' || $event->event_name !== 'vendor.invoice.posted') {
            return $this->markFailed($event, 'unsupported_vendor_invoice_event');
        }

        $rule = PostingRule::query()
            ->with('lines')
            ->where('company_id', $event->company_id)
            ->where('module_name', 'accounts_payable')
            ->where('event_name', $event->event_name)
            ->where('transaction_type', $transactionType)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $eventDate)
            ->where(function ($query) use ($eventDate) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $eventDate);
            })
            ->orderBy('priority')
            ->orderByDesc('version')
            ->first();

        if (! $rule) {
            return $this->markFailed($event, 'posting_rule_not_found');
        }

        [$preview, $error] = $this->buildPreview($event, $rule, $payload);

        if ($error !== null) {
            return $this->markFailed($event, $error);
        }

        $event->update([
            'payload_json' => array_merge($payload, [
                '_posting_rule' => [
                    'id' => $rule->id,
                    'rule_code' => $rule->rule_code,
                    'version' => $rule->version,
                ],
                '_posting_preview' => $preview,
            ]),
            'processing_status' => 'validated',
            'processed_at' => now(),
            'error_message' => null,
        ]);

        $this->lifecycle->resolveOpenFailures($event);
        $this->lifecycle->log($event, 'info', 'Vendor invoice validation completed.', [
            'rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
        ]);

        return [
            'status' => 'validated',
            'error' => null,
        ];
    }

    private function buildPreview(IntegrationEvent $event, PostingRule $rule, array $payload): array
    {
        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rule->lines as $line) {
            $resolvedMappingKey = $this->resolveDynamicMappingKey($line->mapping_key, $payload);

            if ($line->account_source_type === 'mapping' && blank($resolvedMappingKey)) {
                return [[], 'invalid_mapping_key_placeholder'];
            }

            $accountId = $this->resolveAccountId(
                $event,
                $line->account_source_type,
                $line->fixed_account_id,
                $resolvedMappingKey
            );

            if (! $accountId) {
                return [[], 'account_mapping_not_found'];
            }

            $amount = $this->resolveAmount($payload, (string) $line->amount_source, $line->formula_json);

            if ($amount <= 0) {
                return [[], 'invalid_line_amount'];
            }

            if ($line->line_side === 'debit') {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }

            $lines[] = [
                'line_no' => $line->line_no,
                'line_side' => $line->line_side,
                'account_id' => $accountId,
                'mapping_key' => $resolvedMappingKey,
                'amount' => round($amount, 2),
                'description_template' => $line->description_template,
            ];
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return [[], 'unbalanced_preview'];
        }

        return [[
            'currency_code' => $payload['currency_code'] ?? null,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'lines' => $lines,
        ], null];
    }

    private function resolveAmount(array $payload, string $amountSource, ?array $formulaJson = null): float
    {
        return match ($amountSource) {
            'payload_total' => $this->resolvePayloadTotal($payload),
            'payload_tax' => (float) ($payload['amounts']['tax'] ?? 0),
            'payload_net' => (float) ($payload['amounts']['net'] ?? 0),
            'formula' => $this->resolveFormulaAmount($payload, $formulaJson),
            default => 0,
        };
    }

    private function resolveFormulaAmount(array $payload, ?array $formulaJson): float
    {
        if (! is_array($formulaJson)) {
            return 0;
        }

        if (($formulaJson['type'] ?? null) === 'path') {
            return (float) data_get($payload, (string) ($formulaJson['path'] ?? ''), 0);
        }

        return 0;
    }

    private function resolvePayloadTotal(array $payload): float
    {
        if (isset($payload['amounts']['total'])) {
            return (float) $payload['amounts']['total'];
        }

        if (isset($payload['total_amount'])) {
            return (float) $payload['total_amount'];
        }

        if (isset($payload['amount'])) {
            return (float) $payload['amount'];
        }

        $lineTotal = collect($payload['lines'] ?? [])
            ->sum(fn ($line) => (float) ($line['qty'] ?? 0) * (float) ($line['unit_cost'] ?? 0));

        return (float) $lineTotal;
    }

    private function resolveAccountId(IntegrationEvent $event, string $sourceType, ?int $fixedAccountId, ?string $mappingKey): ?int
    {
        if ($sourceType === 'fixed') {
            return $fixedAccountId;
        }

        if ($sourceType === 'mapping' && filled($mappingKey)) {
            return (int) (DB::table('coa_mappings')
                ->where('company_id', $event->company_id)
                ->where('module_name', 'accounts_payable')
                ->where('mapping_key', $mappingKey)
                ->value('account_id') ?? 0) ?: null;
        }

        if ($sourceType === 'payload') {
            $payloadAccountCode = data_get($event->payload_json, 'account_code');

            if (! filled($payloadAccountCode)) {
                return null;
            }

            return ChartOfAccount::query()
                ->where('company_id', $event->company_id)
                ->where('code', $payloadAccountCode)
                ->value('id');
        }

        return null;
    }

    private function resolveDynamicMappingKey(?string $mappingKey, array $payload): ?string
    {
        if (blank($mappingKey)) {
            return $mappingKey;
        }

        if (! str_contains($mappingKey, '{')) {
            return $mappingKey;
        }

        $resolved = preg_replace_callback('/\{(.*?)\}/', function ($matches) use ($payload) {
            $key = $matches[1];
            $value = data_get($payload, $key);

            if (is_array($value) || is_object($value) || $value === null || $value === '') {
                return '';
            }

            return (string) $value;
        }, $mappingKey);

        return str_contains($resolved, '{}') ? null : $resolved;
    }

    private function markFailed(IntegrationEvent $event, string $error): array
    {
        $event->update([
            'processing_status' => 'failed',
            'processed_at' => now(),
            'error_message' => $error,
        ]);

        $this->lifecycle->recordFailure($event, 'validation', $error, 'Vendor invoice validation failed: ' . $error);
        $this->lifecycle->log($event, 'error', 'Vendor invoice validation failed.', [
            'error_code' => $error,
        ]);

        return [
            'status' => 'failed',
            'error' => $error,
        ];
    }
}