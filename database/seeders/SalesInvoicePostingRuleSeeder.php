<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CoaMapping;
use App\Models\Company;
use App\Models\PostingRule;
use Illuminate\Database\Seeder;

class SalesInvoicePostingRuleSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $this->seedSalesInvoiceRule($company);
                $this->seedCustomerInvoiceCollectionRule($company);
            }
        });
    }

    private function seedSalesInvoiceRule(Company $company): void
    {
        $rule = PostingRule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'rule_code' => 'SALES_INVOICE_POSTED_COMBINED',
                'version' => 1,
            ],
            [
                'module_name' => 'sales',
                'event_name' => 'sales.invoice.posted',
                'transaction_type' => 'sales.invoice.standard',
                'rule_name' => 'Sales Invoice Posted Combined Journal',
                'effective_from' => '2026-01-01',
                'priority' => 100,
                'is_active' => true,
                'description' => 'Combined sales invoice and COGS posting. VAT is calculated after discount when tax_rate is supplied.',
            ]
        );

        $lines = [
            [
                'line_no' => 1,
                'line_side' => 'debit',
                'mapping_key' => 'sales.invoice.debit.ar',
                'formula_json' => ['type' => 'sales_invoice_receivable_total'],
                'description_template' => 'Sales invoice accounts receivable',
            ],
            [
                'line_no' => 2,
                'line_side' => 'debit',
                'mapping_key' => 'sales.invoice.debit.discount',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.discount'],
                'description_template' => 'Sales invoice discount',
            ],
            [
                'line_no' => 3,
                'line_side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.revenue',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.subtotal'],
                'description_template' => 'Sales invoice revenue',
            ],
            [
                'line_no' => 4,
                'line_side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.vat_output',
                'formula_json' => ['type' => 'sales_invoice_tax_after_discount'],
                'description_template' => 'Sales invoice VAT output after discount',
            ],
            [
                'line_no' => 5,
                'line_side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.freight_income',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.shipping_fee'],
                'description_template' => 'Sales invoice shipping income',
            ],
            [
                'line_no' => 6,
                'line_side' => 'debit',
                'mapping_key' => 'sales.invoice.debit.cogs',
                'formula_json' => ['type' => 'sales_invoice_cogs_total'],
                'description_template' => 'Sales invoice COGS from dispatch cost',
            ],
            [
                'line_no' => 7,
                'line_side' => 'credit',
                'mapping_key' => 'sales.invoice.credit.inventory',
                'formula_json' => ['type' => 'sales_invoice_cogs_total'],
                'description_template' => 'Sales invoice inventory reduction from dispatch cost',
            ],
        ];

        $rule->lines()->whereNotIn('line_no', collect($lines)->pluck('line_no')->all())->delete();

        foreach ($lines as $line) {
            $rule->lines()->updateOrCreate(
                ['line_no' => $line['line_no']],
                [
                    'line_side' => $line['line_side'],
                    'account_source_type' => 'mapping',
                    'fixed_account_id' => null,
                    'mapping_key' => $line['mapping_key'],
                    'amount_source' => 'formula',
                    'formula_json' => $line['formula_json'],
                    'dimension_rule_json' => null,
                    'description_template' => $line['description_template'],
                ]
            );
        }

        $this->seedDefaultMappings($company, [
            'sales.invoice.debit.ar' => [
                'account_codes' => ['1130'],
                'description' => 'Accounts receivable for posted sales invoices',
            ],
            'sales.invoice.debit.discount' => [
                'account_codes' => ['7130', '4120'],
                'description' => 'Sales discount / contra revenue account',
            ],
            'sales.invoice.credit.revenue' => [
                'account_codes' => ['4120'],
                'description' => 'Trading revenue account for posted sales invoices',
            ],
            'sales.invoice.credit.vat_output' => [
                'account_codes' => ['2130'],
                'description' => 'VAT output / tax payable account',
            ],
            'sales.invoice.credit.freight_income' => [
                'account_codes' => ['4120'],
                'description' => 'Shipping income account for sales invoices',
            ],
            'sales.invoice.debit.cogs' => [
                'account_codes' => ['5120'],
                'description' => 'Cost of goods sold from dispatched inventory cost',
            ],
            'sales.invoice.credit.inventory' => [
                'account_codes' => ['1150'],
                'description' => 'Inventory account reduced by dispatched inventory cost',
            ],
        ]);
    }


    private function seedCustomerInvoiceCollectionRule(Company $company): void
    {
        $rule = PostingRule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'rule_code' => 'CUSTOMER_INVOICE_COLLECTION_POSTED',
                'version' => 1,
            ],
            [
                'module_name' => 'sales',
                'event_name' => 'customer.invoice.collection.posted',
                'transaction_type' => 'customer.invoice.collection',
                'rule_name' => 'Customer Invoice Collection Posting Rule',
                'effective_from' => '2026-01-01',
                'priority' => 100,
                'is_active' => true,
                'description' => 'Customer invoice collection journal: selected cash/bank and deductions debit, AR and other charges credit, balanced to net cash in.',
            ]
        );

        $lines = [
            [
                'line_no' => 1,
                'line_side' => 'debit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'sales.collection.debit.cash_bank',
                'formula_json' => ['type' => 'customer_collection_cash_in'],
                'description_template' => 'Customer invoice collection net cash in',
            ],
            [
                'line_no' => 2,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'sales.collection.debit.wht',
                'formula_json' => ['type' => 'customer_collection_wht_total'],
                'description_template' => 'Customer invoice collection withholding tax',
            ],
            [
                'line_no' => 3,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'sales.collection.debit.other_deduction',
                'formula_json' => ['type' => 'customer_collection_other_deduction_total'],
                'description_template' => 'Customer invoice collection other deduction',
            ],
            [
                'line_no' => 4,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'sales.collection.debit.bank_charge',
                'formula_json' => ['type' => 'customer_collection_bank_charge_total'],
                'description_template' => 'Customer invoice collection bank charge',
            ],
            [
                'line_no' => 5,
                'line_side' => 'credit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'sales.collection.credit.ar',
                'formula_json' => ['type' => 'customer_collection_invoice_total'],
                'description_template' => 'Customer invoice accounts receivable settlement',
            ],
            [
                'line_no' => 6,
                'line_side' => 'credit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'sales.collection.credit.other_charge',
                'formula_json' => ['type' => 'customer_collection_other_charge_total'],
                'description_template' => 'Customer invoice collection other charge',
            ],
        ];

        $rule->lines()->whereNotIn('line_no', collect($lines)->pluck('line_no')->all())->delete();

        foreach ($lines as $line) {
            $rule->lines()->updateOrCreate(
                ['line_no' => $line['line_no']],
                [
                    'line_side' => $line['line_side'],
                    'account_source_type' => $line['account_source_type'],
                    'fixed_account_id' => null,
                    'mapping_key' => $line['mapping_key'],
                    'amount_source' => 'formula',
                    'formula_json' => $line['formula_json'],
                    'dimension_rule_json' => null,
                    'description_template' => $line['description_template'],
                ]
            );
        }

        $this->seedDefaultMappings($company, [
            'sales.collection.debit.wht' => [
                'account_codes' => ['1160'],
                'description' => 'Withholding tax receivable/prepaid tax for customer invoice collections',
            ],
            'sales.collection.debit.other_deduction' => [
                'account_codes' => ['7130', '7160'],
                'description' => 'Other deductions for customer invoice collections',
            ],
            'sales.collection.debit.bank_charge' => [
                'account_codes' => ['7160'],
                'description' => 'Bank charge expense for customer invoice collections',
            ],
            'sales.collection.credit.ar' => [
                'account_codes' => ['1130'],
                'description' => 'Accounts receivable settled by customer invoice collections',
            ],
            'sales.collection.credit.other_charge' => [
                'account_codes' => ['4120', '8110'],
                'description' => 'Other charge income from customer invoice collections',
            ],
        ]);
    }

    private function seedDefaultMappings(Company $company, array $mappings): void
    {
        foreach ($mappings as $mappingKey => $mapping) {
            $accountId = null;

            foreach ($mapping['account_codes'] as $accountCode) {
                $accountId = ChartOfAccount::query()
                    ->where('company_id', $company->id)
                    ->where('code', $accountCode)
                    ->value('id');

                if ($accountId) {
                    break;
                }
            }

            if (! $accountId) {
                continue;
            }

            CoaMapping::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'module_name' => 'sales',
                    'mapping_key' => $mappingKey,
                ],
                [
                    'account_id' => $accountId,
                    'description' => $mapping['description'],
                ]
            );
        }
    }
}
