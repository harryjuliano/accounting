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

                $this->seedPostingRule($company, [
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

                $this->seedPostingRule($company, [
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

                $this->seedPostingRule($company, [
                    'rule_code' => 'INV_ISSUE_SALES',
                    'event_name' => 'inventory.issue.posted',
                    'transaction_type' => 'inventory.issue.sales',
                    'rule_name' => 'Inventory Issue Sales Rule',
                    'description' => 'Debit COGS and credit inventory asset for sales inventory issues.',
                    'lines' => [
                        [
                            'line_no' => 1,
                            'line_side' => 'debit',
                            'mapping_key' => 'inventory.issue.sales.debit.cogs',
                            'description_template' => 'Inventory issue sales COGS',
                        ],
                        [
                            'line_no' => 2,
                            'line_side' => 'credit',
                            'mapping_key' => 'inventory.issue.sales.credit.inventory',
                            'description_template' => 'Inventory issue sales inventory reduction',
                        ],
                    ],
                    'default_mappings' => [
                        'inventory.issue.sales.debit.cogs' => [
                            'account_code' => '5120',
                            'description' => 'COGS account for sales inventory issues',
                        ],
                        'inventory.issue.sales.credit.inventory' => [
                            'account_code' => '1150',
                            'description' => 'Inventory account for sales inventory issues',
                        ],
                    ],
                ]);

                $this->seedPostingRule($company, [
                    'rule_code' => 'INV_ISSUE_DAMAGED',
                    'event_name' => 'inventory.issue.posted',
                    'transaction_type' => 'inventory.issue.damaged',
                    'rule_name' => 'Inventory Issue Damaged Rule',
                    'description' => 'Debit inventory loss/write-off and credit inventory asset for damaged stock.',
                    'lines' => [
                        [
                            'line_no' => 1,
                            'line_side' => 'debit',
                            'mapping_key' => 'inventory.issue.damaged.debit.loss',
                            'description_template' => 'Inventory damaged stock write-off',
                        ],
                        [
                            'line_no' => 2,
                            'line_side' => 'credit',
                            'mapping_key' => 'inventory.issue.damaged.credit.inventory',
                            'description_template' => 'Inventory damaged stock reduction',
                        ],
                    ],
                    'default_mappings' => [
                        'inventory.issue.damaged.debit.loss' => [
                            'account_code' => '8100',
                            'description' => 'Loss/write-off account for damaged inventory',
                        ],
                        'inventory.issue.damaged.credit.inventory' => [
                            'account_code' => '1150',
                            'description' => 'Inventory account for damaged inventory issues',
                        ],
                    ],
                ]);

                $this->seedPostingRule($company, [
                    'rule_code' => 'INV_ISSUE_SAMPLE',
                    'event_name' => 'inventory.issue.posted',
                    'transaction_type' => 'inventory.issue.sample',
                    'rule_name' => 'Inventory Issue Sample Rule',
                    'description' => 'Debit promotion/sample expense and credit inventory asset for sample stock.',
                    'lines' => [
                        [
                            'line_no' => 1,
                            'line_side' => 'debit',
                            'mapping_key' => 'inventory.issue.sample.debit.promotion',
                            'description_template' => 'Inventory sample promotion expense',
                        ],
                        [
                            'line_no' => 2,
                            'line_side' => 'credit',
                            'mapping_key' => 'inventory.issue.sample.credit.inventory',
                            'description_template' => 'Inventory sample stock reduction',
                        ],
                    ],
                    'default_mappings' => [
                        'inventory.issue.sample.debit.promotion' => [
                            'account_code' => '7100',
                            'description' => 'Promotion/sample expense account for sample inventory issues',
                        ],
                        'inventory.issue.sample.credit.inventory' => [
                            'account_code' => '1150',
                            'description' => 'Inventory account for sample inventory issues',
                        ],
                    ],
                ]);

                $this->seedPostingRule($company, [
                    'rule_code' => 'INV_ISSUE_INTERNAL_USE',
                    'event_name' => 'inventory.issue.posted',
                    'transaction_type' => 'inventory.issue.internal_use',
                    'rule_name' => 'Inventory Issue Internal Use Rule',
                    'description' => 'Debit internal use expense and credit inventory asset for internal stock consumption.',
                    'lines' => [
                        [
                            'line_no' => 1,
                            'line_side' => 'debit',
                            'mapping_key' => 'inventory.issue.internal_use.debit.expense',
                            'description_template' => 'Inventory internal use expense',
                        ],
                        [
                            'line_no' => 2,
                            'line_side' => 'credit',
                            'mapping_key' => 'inventory.issue.internal_use.credit.inventory',
                            'description_template' => 'Inventory internal use stock reduction',
                        ],
                    ],
                    'default_mappings' => [
                        'inventory.issue.internal_use.debit.expense' => [
                            'account_code' => '7100',
                            'description' => 'Internal use expense account for inventory issues',
                        ],
                        'inventory.issue.internal_use.credit.inventory' => [
                            'account_code' => '1150',
                            'description' => 'Inventory account for internal use inventory issues',
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

    private function seedPostingRule(Company $company, array $definition): void
    {
        $rule = PostingRule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'rule_code' => $definition['rule_code'],
                'version' => 1,
            ],
            [
                'module_name' => 'inventory',
                'event_name' => $definition['event_name'] ?? 'inventory.receipt.posted',
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
