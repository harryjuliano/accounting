<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CoaMapping;
use App\Models\Company;
use App\Models\PostingRule;
use Illuminate\Database\Seeder;

class VendorPaymentPostingRuleSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $this->seedVendorPaymentRule($company);
            }
        });
    }

    private function seedVendorPaymentRule(Company $company): void
    {
        $rule = PostingRule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'rule_code' => 'AP_VENDOR_PAYMENT_BANK_TRANSFER',
                'version' => 1,
            ],
            [
                'module_name' => 'accounts_payable',
                'event_name' => 'vendor.payment.posted',
                'transaction_type' => 'vendor.payment.bank_transfer',
                'rule_name' => 'Vendor Payment Bank Transfer Posting Rule',
                'effective_from' => '2026-01-01',
                'priority' => 100,
                'is_active' => true,
                'description' => 'Debit AP and payment-related expenses, credit WHT payable and selected cash/bank account.',
            ]
        );

        $lines = [
            [
                'line_no' => 1,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'vendor.payment.debit.ap',
                'formula_json' => ['type' => 'vendor_payment_invoice_total'],
                'description_template' => 'Vendor payment accounts payable settlement',
            ],
            [
                'line_no' => 2,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'vendor.payment.debit.stamp_duty',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.stamp_duty'],
                'description_template' => 'Vendor payment stamp duty',
            ],
            [
                'line_no' => 3,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'vendor.payment.debit.bank_charge',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.bank_charge'],
                'description_template' => 'Vendor payment bank charge',
            ],
            [
                'line_no' => 4,
                'line_side' => 'debit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'vendor.payment.debit.freight',
                'formula_json' => ['type' => 'path', 'path' => 'amounts.freight'],
                'description_template' => 'Vendor payment freight',
            ],
            [
                'line_no' => 5,
                'line_side' => 'credit',
                'account_source_type' => 'mapping',
                'mapping_key' => 'vendor.payment.credit.wht',
                'formula_json' => ['type' => 'vendor_payment_wht_total'],
                'description_template' => 'Vendor payment withholding tax payable',
            ],
            [
                'line_no' => 6,
                'line_side' => 'credit',
                'account_source_type' => 'dynamic',
                'mapping_key' => 'vendor.payment.credit.cash_bank',
                'formula_json' => ['type' => 'vendor_payment_cash_out'],
                'description_template' => 'Vendor payment cash/bank out',
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
            'vendor.payment.debit.ap' => [
                'account_codes' => ['2110'],
                'description' => 'Accounts payable account settled by vendor payments',
            ],
            'vendor.payment.debit.stamp_duty' => [
                'account_codes' => ['7120-050', '7120'],
                'description' => 'Stamp duty expense account for vendor payments',
            ],
            'vendor.payment.debit.bank_charge' => [
                'account_codes' => ['7160-050', '7160'],
                'description' => 'Bank charge expense account for vendor payments',
            ],
            'vendor.payment.debit.freight' => [
                'account_codes' => ['7130-020', '7130'],
                'description' => 'Freight expense account for vendor payments',
            ],
            'vendor.payment.credit.wht' => [
                'account_codes' => ['2130'],
                'description' => 'Withholding tax payable account for vendor payments',
            ],
        ]);
    }

    private function seedDefaultMappings(Company $company, array $mappings): void
    {
        foreach ($mappings as $mappingKey => $mapping) {
            $accountId = ChartOfAccount::query()
                ->where('company_id', $company->id)
                ->whereIn('code', $mapping['account_codes'])
                ->orderByRaw('case code ' . collect($mapping['account_codes'])->map(fn ($code, $index) => 'when ? then ' . $index)->implode(' ') . ' end', $mapping['account_codes'])
                ->value('id');

            if (! $accountId) {
                continue;
            }

            CoaMapping::query()->updateOrCreate(
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
