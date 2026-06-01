<?php

namespace App\Services\Integrations;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\IntegrationEvent;
use App\Models\PostingRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesInvoicePostingRuleEngine
{
    public function __construct(private readonly IntegrationEventLifecycleService $lifecycle)
    {
    }

    public function validateAndMark(IntegrationEvent $event): array
    {
        $eventDate = Carbon::parse($event->event_datetime)->toDateString();
        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        $transactionType = (string) ($payload['transaction_type'] ?? $this->defaultTransactionType($event));

        $this->lifecycle->log($event, 'info', 'Sales invoice validation started.', [
            'event_name' => $event->event_name,
            'transaction_type' => $transactionType,
        ]);

        if (! $this->isSupportedSalesEvent($event)) {
            return $this->markFailed($event, 'unsupported_sales_event');
        }

        $rule = PostingRule::query()
            ->with('lines')
            ->where('company_id', $event->company_id)
            ->where('module_name', 'sales')
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
                'posting_mode' => PostingMode::MODULE_PRESET,
                '_posting_mode' => PostingMode::MODULE_PRESET,
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
        $this->lifecycle->log($event, 'info', 'Sales invoice validation completed.', [
            'rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
            'total_debit' => $preview['total_debit'],
            'total_credit' => $preview['total_credit'],
        ]);

        return [
            'status' => 'validated',
            'error' => null,
        ];
    }

    private function isSupportedSalesEvent(IntegrationEvent $event): bool
    {
        return $event->source_module === 'sales'
            && in_array($event->event_name, ['sales.invoice.posted', 'customer.invoice.collection.posted'], true);
    }

    private function defaultTransactionType(IntegrationEvent $event): string
    {
        return $event->event_name === 'customer.invoice.collection.posted'
            ? 'customer.invoice.collection'
            : 'sales.invoice.standard';
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
                'amount' => $amount,
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

    private function resolveAmount(array $payload, string $amountSource, ?array $formulaJson = null): float
    {
        return match ($amountSource) {
            'payload_total' => $this->salesSubtotal($payload),
            'payload_tax' => $this->salesTaxAfterDiscount($payload),
            'payload_net' => $this->salesReceivableTotal($payload),
            'formula' => $this->resolveFormulaAmount($payload, $formulaJson),
            default => 0,
        };
    }

    private function resolveFormulaAmount(array $payload, ?array $formulaJson): float
    {
        if (! is_array($formulaJson)) {
            return 0;
        }

        return match ($formulaJson['type'] ?? null) {
            'path' => (float) data_get($payload, (string) ($formulaJson['path'] ?? ''), 0),
            'sales_invoice_tax_after_discount' => $this->salesTaxAfterDiscount($payload),
            'sales_invoice_receivable_total' => $this->salesReceivableTotal($payload),
            'sales_invoice_cogs_total' => $this->salesCogsTotal($payload),
            'customer_collection_invoice_total' => $this->customerCollectionInvoiceTotal($payload),
            'customer_collection_other_charge_total' => $this->customerCollectionOtherChargeTotal($payload),
            'customer_collection_wht_total' => $this->customerCollectionWhtTotal($payload),
            'customer_collection_other_deduction_total' => $this->customerCollectionOtherDeductionTotal($payload),
            'customer_collection_bank_charge_total' => $this->customerCollectionBankChargeTotal($payload),
            'customer_collection_cash_in' => $this->customerCollectionCashIn($payload),
            default => 0,
        };
    }

    private function salesSubtotal(array $payload): float
    {
        foreach (['amounts.subtotal', 'sales_invoice.subtotal', 'subtotal', 'total_amount', 'amount'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['lines'] ?? $payload['lines_detail'] ?? [])
            ->sum(fn ($line) => (float) ($line['sales_amount'] ?? ((float) ($line['qty'] ?? 0) * (float) ($line['selling_price'] ?? $line['unit_price'] ?? 0))));
    }

    private function salesDiscount(array $payload): float
    {
        foreach (['amounts.discount', 'sales_invoice.discount', 'discount'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['lines'] ?? $payload['lines_detail'] ?? [])
            ->sum(fn ($line) => (float) ($line['discount_amount'] ?? $line['discount'] ?? 0));
    }

    private function salesShippingFee(array $payload): float
    {
        foreach (['amounts.shipping_fee', 'amounts.freight', 'sales_invoice.shipping_fee', 'shipping_fee', 'freight'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return 0;
    }

    private function salesTaxAfterDiscount(array $payload): float
    {
        $taxableBase = max(0, $this->salesSubtotal($payload) - $this->salesDiscount($payload));
        $taxRate = data_get($payload, 'tax.rate', data_get($payload, 'amounts.tax_rate', data_get($payload, 'sales_invoice.tax_rate')));

        if ($taxRate !== null) {
            $taxRate = (float) $taxRate;
            $taxRate = $taxRate > 1 ? $taxRate / 100 : $taxRate;

            return round($taxableBase * $taxRate, 2);
        }

        return (float) data_get($payload, 'amounts.tax', data_get($payload, 'sales_invoice.tax_amount', data_get($payload, 'tax_amount', 0)));
    }

    private function salesReceivableTotal(array $payload): float
    {
        $explicitNet = data_get($payload, 'amounts.net_invoice', data_get($payload, 'sales_invoice.net_invoice'));

        if ($explicitNet !== null && data_get($payload, 'tax.rate', data_get($payload, 'amounts.tax_rate', data_get($payload, 'sales_invoice.tax_rate'))) === null) {
            return (float) $explicitNet;
        }

        return $this->salesSubtotal($payload)
            - $this->salesDiscount($payload)
            + $this->salesTaxAfterDiscount($payload)
            + $this->salesShippingFee($payload);
    }

    private function salesCogsTotal(array $payload): float
    {
        foreach (['amounts.cogs', 'dispatch_cost.total_cogs', 'cogs_total', 'cost_amount'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['lines'] ?? $payload['lines_detail'] ?? [])
            ->sum(fn ($line) => (float) ($line['cost_amount'] ?? ((float) ($line['qty'] ?? 0) * (float) ($line['unit_cost'] ?? $line['cost_price'] ?? 0))));
    }


    private function customerCollectionInvoiceTotal(array $payload): float
    {
        foreach (['amounts.invoice_total', 'amounts.invoice_amount', 'amounts.collection_invoice_total', 'amounts.total_invoice', 'amounts.total'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['invoice_lines'] ?? [])
            ->sum(fn ($line) => (float) ($line['invoice_amount'] ?? $line['collection_amount'] ?? $line['payment_amount'] ?? 0));
    }

    private function customerCollectionOtherChargeTotal(array $payload): float
    {
        foreach (['amounts.other_charge', 'amounts.other_charges', 'amounts.admin_charge', 'amounts.late_fee'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['other_charges'] ?? [])
            ->sum(fn ($line) => (float) ($line['amount'] ?? 0));
    }

    private function customerCollectionWhtTotal(array $payload): float
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

    private function customerCollectionOtherDeductionTotal(array $payload): float
    {
        foreach (['amounts.other_deduction', 'amounts.other_deductions', 'amounts.deduction', 'amounts.deductions', 'amounts.discount', 'amounts.discount_total'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return (float) collect($payload['deductions'] ?? [])
            ->sum(fn ($line) => (float) ($line['amount'] ?? 0));
    }

    private function customerCollectionBankChargeTotal(array $payload): float
    {
        foreach (['amounts.bank_charge', 'amounts.bank_charges', 'amounts.bank_fee'] as $path) {
            $amount = data_get($payload, $path);

            if ($amount !== null) {
                return (float) $amount;
            }
        }

        return 0;
    }

    private function customerCollectionCashIn(array $payload): float
    {
        $explicitCashIn = data_get($payload, 'amounts.net_cash_in', data_get($payload, 'amounts.cash_in'));

        if ($explicitCashIn !== null) {
            return (float) $explicitCashIn;
        }

        return $this->customerCollectionInvoiceTotal($payload)
            + $this->customerCollectionOtherChargeTotal($payload)
            - $this->customerCollectionWhtTotal($payload)
            - $this->customerCollectionOtherDeductionTotal($payload)
            - $this->customerCollectionBankChargeTotal($payload);
    }

    private function resolveMissingAccountError(string $sourceType, ?string $mappingKey): string
    {
        if ($sourceType === 'dynamic' && $mappingKey === 'sales.collection.debit.cash_bank') {
            return 'cash_bank_account_not_found';
        }

        return 'account_mapping_not_found';
    }

    private function resolveAccountId(IntegrationEvent $event, string $sourceType, ?int $fixedAccountId, ?string $mappingKey, array $payload): ?int
    {
        if ($sourceType === 'fixed') {
            return $fixedAccountId;
        }

        if ($sourceType === 'mapping' && filled($mappingKey)) {
            return (int) (DB::table('coa_mappings')
                ->where('company_id', $event->company_id)
                ->where('module_name', 'sales')
                ->where('mapping_key', $mappingKey)
                ->value('account_id') ?? 0) ?: null;
        }

        if ($sourceType === 'dynamic' && $mappingKey === 'sales.collection.debit.cash_bank') {
            return $this->resolveSelectedCashAccount($event, $payload);
        }

        if ($sourceType === 'payload') {
            $payloadAccountCode = data_get($payload, 'account_code');

            if (! filled($payloadAccountCode)) {
                return null;
            }

            return ChartOfAccount::query()
                ->where('company_id', $event->company_id)
                ->where('code', $payloadAccountCode)
                ->where('is_active', true)
                ->value('id');
        }

        return null;
    }



    private function resolveSelectedCashAccount(IntegrationEvent $event, array $payload): ?int
    {
        $glAccountCode = data_get($payload, 'gl_account_code');

        if (filled($glAccountCode)) {
            return $this->resolveCashGlAccountByCode($event, (string) $glAccountCode);
        }

        $cashAccountCoaId = data_get($payload, 'cash_account_coa_id');

        if (is_numeric($cashAccountCoaId) && (int) $cashAccountCoaId > 0) {
            return $this->resolveCashGlAccountById($event, (int) $cashAccountCoaId);
        }

        $cashAccountId = data_get($payload, 'cash_account_id', data_get($payload, 'bank_account_id'));

        if (is_numeric($cashAccountId) && (int) $cashAccountId > 0) {
            return $this->resolveBankAccountGlAccountId($event, (int) $cashAccountId);
        }

        return null;
    }

    private function resolveBankAccountGlAccountId(IntegrationEvent $event, int $cashAccountId): ?int
    {
        $bankAccount = BankAccount::query()
            ->where('company_id', $event->company_id)
            ->whereKey($cashAccountId)
            ->where('is_active', true)
            ->first();

        if (! $bankAccount) {
            return null;
        }

        return $this->resolveCashGlAccountById($event, (int) $bankAccount->gl_account_id);
    }

    private function resolveCashGlAccountById(IntegrationEvent $event, int $accountId): ?int
    {
        return (int) (ChartOfAccount::query()
            ->where('company_id', $event->company_id)
            ->whereKey($accountId)
            ->where('is_active', true)
            ->whereIn('account_type', ['asset', 'assets'])
            ->value('id') ?? 0) ?: null;
    }

    private function resolveCashGlAccountByCode(IntegrationEvent $event, string $accountCode): ?int
    {
        return (int) (ChartOfAccount::query()
            ->where('company_id', $event->company_id)
            ->where('code', $accountCode)
            ->where('is_active', true)
            ->whereIn('account_type', ['asset', 'assets'])
            ->value('id') ?? 0) ?: null;
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
            $value = data_get($payload, $matches[1]);

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

        $this->lifecycle->recordFailure($event, 'validation', $error, 'Sales invoice validation failed: ' . $error);
        $this->lifecycle->log($event, 'error', 'Sales invoice validation failed.', [
            'error_code' => $error,
        ]);

        return [
            'status' => 'failed',
            'error' => $error,
        ];
    }
}
