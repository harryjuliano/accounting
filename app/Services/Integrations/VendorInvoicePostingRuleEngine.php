<?php

namespace App\Services\Integrations;

use App\Models\BankAccount;
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

        $this->lifecycle->log($event, 'info', 'Accounts payable validation started.', [
            'event_name' => $event->event_name,
            'transaction_type' => $transactionType,
        ]);

        if (! $this->isSupportedAccountsPayableEvent($event)) {
            return $this->markFailed($event, 'unsupported_accounts_payable_event');
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
        $this->lifecycle->log($event, 'info', 'Accounts payable validation completed.', [
            'rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
        ]);

        return [
            'status' => 'validated',
            'error' => null,
        ];
    }

    private function isSupportedAccountsPayableEvent(IntegrationEvent $event): bool
    {
        return $event->source_module === 'accounts_payable'
            && in_array($event->event_name, ['vendor.invoice.posted', 'vendor.payment.posted'], true);
    }

    private function buildPreview(IntegrationEvent $event, PostingRule $rule, array $payload): array
    {
        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rule->lines as $line) {
            $amount = round($this->resolveAmount($payload, (string) $line->amount_source, $line->formula_json), 2);

            if ($amount < 0) {
                return [[], 'invalid_line_amount'];
            }

            if ($amount === 0.0) {
                continue;
            }

            $resolvedMappingKey = $this->resolveDynamicMappingKey($line->mapping_key, $payload);

            if ($line->account_source_type === 'mapping' && blank($resolvedMappingKey)) {
                return [[], 'invalid_mapping_key_placeholder'];
            }

            $accountId = $this->resolveAccountId(
                $event,
                $line->account_source_type,
                $line->fixed_account_id,
                $resolvedMappingKey,
                $payload
            );

            if (! $accountId) {
                return [[], $this->resolveMissingAccountError($line->account_source_type, $resolvedMappingKey)];
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

        if ($lines === []) {
            return [[], 'invalid_line_amount'];
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

    private function resolveMissingAccountError(string $sourceType, ?string $mappingKey): string
    {
        if ($sourceType === 'dynamic' && $mappingKey === 'vendor.payment.credit.cash_bank') {
            return 'cash_bank_account_not_found';
        }

        return 'account_mapping_not_found';
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

        if (($formulaJson['type'] ?? null) === 'vendor_payment_invoice_total') {
            return $this->resolveVendorPaymentInvoiceTotal($payload);
        }

        if (($formulaJson['type'] ?? null) === 'vendor_payment_wht_total') {
            return $this->resolveVendorPaymentWhtTotal($payload);
        }

        if (($formulaJson['type'] ?? null) === 'vendor_payment_cash_out') {
            return $this->resolveVendorPaymentCashOut($payload);
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

    private function resolveVendorPaymentInvoiceTotal(array $payload): float
    {
        foreach (['amounts.invoice_payment_total', 'amounts.payment_total', 'amounts.invoice_amount', 'amounts.total'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['invoice_lines'] ?? [])
            ->sum(fn ($line) => (float) ($line['payment_amount'] ?? $line['invoice_amount'] ?? 0));
    }

    private function resolveVendorPaymentWhtTotal(array $payload): float
    {
        foreach (['amounts.withholding_tax_total', 'amounts.wht_total', 'amounts.withholding_tax', 'amounts.wht'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['invoice_lines'] ?? [])
            ->sum(fn ($line) => (float) ($line['withholding_tax'] ?? $line['wht'] ?? 0));
    }

    private function resolveVendorPaymentCashOut(array $payload): float
    {
        $explicitCashOut = data_get($payload, 'amounts.cash_out');

        if ($explicitCashOut !== null) {
            return (float) $explicitCashOut;
        }

        return $this->resolveVendorPaymentInvoiceTotal($payload)
            - $this->resolveVendorPaymentWhtTotal($payload)
            + (float) data_get($payload, 'amounts.stamp_duty', 0)
            + (float) data_get($payload, 'amounts.bank_charge', 0)
            + (float) data_get($payload, 'amounts.freight', 0);
    }

    private function resolveAccountId(IntegrationEvent $event, string $sourceType, ?int $fixedAccountId, ?string $mappingKey, array $payload): ?int
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

        if ($sourceType === 'dynamic' && $mappingKey === 'vendor.payment.credit.cash_bank') {
            return $this->resolveSelectedCashAccount($event, $payload);
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

    private function resolveSelectedCashAccount(IntegrationEvent $event, array $payload): ?int
    {
        $bankAccountId = data_get($payload, 'bank_account_id');

        if (is_numeric($bankAccountId) && (int) $bankAccountId > 0) {
            return $this->resolveBankAccountGlAccountId($event, (int) $bankAccountId);
        }

        $cashAccountId = data_get($payload, 'cash_account_id');

        if (! is_numeric($cashAccountId) || (int) $cashAccountId <= 0) {
            return null;
        }

        $cashAccountId = (int) $cashAccountId;

        return $this->resolveBankAccountGlAccountId($event, $cashAccountId)
            ?? $this->resolveCashChartOfAccountId($event, $cashAccountId);
    }

    private function resolveBankAccountGlAccountId(IntegrationEvent $event, int $bankAccountId): ?int
    {
        $bankAccount = BankAccount::query()
            ->where('company_id', $event->company_id)
            ->whereKey($bankAccountId)
            ->where('is_active', true)
            ->first();

        if (! $bankAccount) {
            return null;
        }

        return $this->resolveCashChartOfAccountId($event, (int) $bankAccount->gl_account_id);
    }

    private function resolveCashChartOfAccountId(IntegrationEvent $event, int $accountId): ?int
    {
        $accountExists = ChartOfAccount::query()
            ->where('company_id', $event->company_id)
            ->whereKey($accountId)
            ->where('is_active', true)
            ->exists();

        return $accountExists ? $accountId : null;
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

        $this->lifecycle->recordFailure($event, 'validation', $error, 'Accounts payable validation failed: ' . $error);
        $this->lifecycle->log($event, 'error', 'Accounts payable validation failed.', [
            'error_code' => $error,
        ]);

        return [
            'status' => 'failed',
            'error' => $error,
        ];
    }
}
