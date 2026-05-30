<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CoaMapping;
use App\Models\Company;
use App\Models\PostingRule;
use Illuminate\Database\Seeder;

class InventoryPostingRuleSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $this->deactivateLegacyReceiptRule($company);

                $this->seedReceiptRule($company, [
                    'rule_code' => 'INV_RECEIPT_PURCHASE',
                    'transaction_type' => 'inventory.receipt.purchase',
                    'rule_name' => 'Inventory Receipt Purchase Rule',
                    'description' => 'Debit inventory asset and credit GRNI/AP clearing for purchase receipts.',
                    'lines' => [
                        [
                            'line_no' => 1,
                            'line_side' => 'debit',
                            'mapping_key' => 'inventory.receipt.purchase.debit.inventory',
                            'description_template' => 'Inventory receipt from purchase',
                        ],
                        [
                            'line_no' => 2,
                            'line_side' => 'credit',
                            'mapping_key' => 'inventory.receipt.purchase.credit.grni',
                            'description_template' => 'Inventory receipt purchase GRNI/AP clearing',
                        ],
                    ],
                    'default_mappings' => [
                        'inventory.receipt.purchase.debit.inventory' => [
                            'account_code' => '1150',
                            'description' => 'Inventory account for purchase receipts',
                        ],
                        'inventory.receipt.purchase.credit.grni' => [
                            'account_code' => '2120',
                            'description' => 'GRNI/accrued liability account for purchase receipts',
                        ],
                    ],
                ]);

                $this->seedReceiptRule($company, [
                    'rule_code' => 'INV_RECEIPT_PURCHASE_RETURN',
                    'transaction_type' => 'inventory.receipt.purchase_return',
                    'rule_name' => 'Inventory Receipt Purchase Return Rule',
                    'description' => 'Debit inventory asset and credit purchase return clearing for purchase return receipts.',
                    'lines' => [
                        [
                            'line_no' => 1,
                            'line_side' => 'debit',
                            'mapping_key' => 'inventory.receipt.purchase_return.debit.inventory',
                            'description_template' => 'Inventory receipt from purchase return',
                        ],
                        [
                            'line_no' => 2,
                            'line_side' => 'credit',
                            'mapping_key' => 'inventory.receipt.purchase_return.credit.clearing',
                            'description_template' => 'Inventory receipt purchase return clearing',
                        ],
                    ],
                    'default_mappings' => [
                        'inventory.receipt.purchase_return.debit.inventory' => [
                            'account_code' => '1150',
                            'description' => 'Inventory account for purchase return receipts',
                        ],
                        'inventory.receipt.purchase_return.credit.clearing' => [
                            'account_code' => '2110',
                            'description' => 'Vendor/AP clearing account for purchase return receipts',
                        ],
                    ],
                ]);
            }
        });
    }

    private function deactivateLegacyReceiptRule(Company $company): void
    {
        PostingRule::query()
            ->where('company_id', $company->id)
            ->where('rule_code', 'INV_RECEIPT_BASIC')
            ->update(['is_active' => false]);
    }

    private function seedReceiptRule(Company $company, array $definition): void
    {
        $rule = PostingRule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'rule_code' => $definition['rule_code'],
                'version' => 1,
            ],
            [
                'module_name' => 'inventory',
                'event_name' => 'inventory.receipt.posted',
                'transaction_type' => $definition['transaction_type'],
                'rule_name' => $definition['rule_name'],
                'effective_from' => '2026-01-01',
                'priority' => 100,
                'is_active' => true,
                'description' => $definition['description'],
            ]
        );

        $lineNumbers = collect($definition['lines'])->pluck('line_no')->all();
        $rule->lines()->whereNotIn('line_no', $lineNumbers)->delete();

        foreach ($definition['lines'] as $line) {
            $rule->lines()->updateOrCreate(
                ['line_no' => $line['line_no']],
                [
                    'line_side' => $line['line_side'],
                    'account_source_type' => 'mapping',
                    'fixed_account_id' => null,
                    'mapping_key' => $line['mapping_key'],
                    'amount_source' => 'payload_total',
                    'formula_json' => null,
                    'dimension_rule_json' => null,
                    'description_template' => $line['description_template'],
                ]
            );
        }

        $this->seedDefaultMappings($company, $definition['default_mappings']);
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
                    'module_name' => 'inventory',
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
