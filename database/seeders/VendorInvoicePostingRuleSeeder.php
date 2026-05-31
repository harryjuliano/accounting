<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CoaMapping;
use App\Models\Company;
use App\Models\PostingRule;
use Illuminate\Database\Seeder;

class VendorInvoicePostingRuleSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $this->seedVendorInvoiceRule($company);
            }
        });
    }

    private function seedVendorInvoiceRule(Company $company): void
    {
        $rule = PostingRule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'rule_code' => 'AP_VENDOR_INVOICE_STANDARD',
                'version' => 1,
            ],
            [
                'module_name' => 'accounts_payable',
                'event_name' => 'vendor.invoice.posted',
                'transaction_type' => 'vendor.invoice.standard',
                'rule_name' => 'Vendor Invoice Standard Posting Rule',
                'effective_from' => '2026-01-01',
                'priority' => 100,
                'is_active' => true,
                'description' => 'Debit invoice/DPP, input VAT, freight and credit purchase discount, withholding tax plus vendor payable.',
            ]
        );

        $lines = [
            [
                'line_no' => 1,
                'line_side' => 'debit',
                'mapping_key' => 'vendor.invoice.debit.invoice',
                'formula_path' => 'amounts.invoice',
                'description_template' => 'Vendor invoice DPP',
            ],
            [
                'line_no' => 2,
                'line_side' => 'debit',
                'mapping_key' => 'vendor.invoice.debit.ppn',
                'formula_path' => 'amounts.tax',
                'description_template' => 'Vendor invoice input VAT',
            ],
            [
                'line_no' => 3,
                'line_side' => 'debit',
                'mapping_key' => 'vendor.invoice.debit.freight',
                'formula_path' => 'amounts.freight',
                'description_template' => 'Vendor invoice freight cost',
            ],
            [
                'line_no' => 4,
                'line_side' => 'credit',
                'mapping_key' => 'vendor.invoice.credit.wht',
                'formula_path' => 'amounts.withholding_tax',
                'description_template' => 'Vendor invoice withholding tax payable',
            ],
            [
                'line_no' => 5,
                'line_side' => 'credit',
                'mapping_key' => 'vendor.invoice.credit.purchase_discount',
                'formula_path' => 'amounts.purchase_discount',
                'description_template' => 'Vendor invoice purchase discount',
            ],
            [
                'line_no' => 6,
                'line_side' => 'credit',
                'mapping_key' => 'vendor.invoice.credit.ap',
                'formula_path' => 'amounts.payable_total',
                'description_template' => 'Vendor invoice accounts payable',
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
                    'formula_json' => [
                        'type' => 'path',
                        'path' => $line['formula_path'],
                    ],
                    'dimension_rule_json' => null,
                    'description_template' => $line['description_template'],
                ]
            );
        }

        $this->seedDefaultMappings($company, [
            'vendor.invoice.debit.invoice' => [
                'account_code' => '5120',
                'description' => 'Default invoice/DPP expense account for vendor invoices',
            ],
            'vendor.invoice.debit.ppn' => [
                'account_code' => '1170',
                'description' => 'Input VAT/prepaid tax account for vendor invoices',
            ],
            'vendor.invoice.debit.freight' => [
                'account_code' => '7130',
                'description' => 'Freight/distribution expense account for vendor invoices',
            ],
            'vendor.invoice.credit.wht' => [
                'account_code' => '2130',
                'description' => 'Withholding tax payable account for vendor invoices',
            ],
            'vendor.invoice.credit.purchase_discount' => [
                'account_code' => '5120',
                'description' => 'Purchase discount account for vendor invoices',
            ],
            'vendor.invoice.credit.ap' => [
                'account_code' => '2110',
                'description' => 'Accounts payable account for vendor invoices',
            ],
        ]);
    }

    private function seedDefaultMappings(Company $company, array $mappings): void
    {
        foreach ($mappings as $mappingKey => $mapping) {
            $accountId = ChartOfAccount::query()
                ->where('company_id', $company->id)
                ->where('code', $mapping['account_code'])
                ->value('id');

            if (! $accountId) {
                continue;
            }

            CoaMapping::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'module_name' => 'accounts_payable',
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
