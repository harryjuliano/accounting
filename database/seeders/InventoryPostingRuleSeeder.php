<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PostingRule;
use Illuminate\Database\Seeder;

class InventoryPostingRuleSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->orderBy('id')->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $rule = PostingRule::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'rule_code' => 'INV_RECEIPT_BASIC',
                        'version' => 1,
                    ],
                    [
                        'module_name' => 'inventory',
                        'event_name' => 'inventory.receipt.posted',
                        'transaction_type' => 'inventory.receipt.posted',
                        'rule_name' => 'Inventory Receipt Basic Rule',
                        'effective_from' => now()->toDateString(),
                        'priority' => 100,
                        'is_active' => true,
                        'description' => 'Debit inventory asset and credit GRNI from payload total amount.',
                    ]
                );

                $rule->lines()->updateOrCreate(
                    ['line_no' => 1],
                    [
                        'line_side' => 'debit',
                        'account_source_type' => 'mapping',
                        'mapping_key' => 'inventory.receipt.debit.asset',
                        'amount_source' => 'payload_total',
                        'description_template' => 'Inventory receipt asset posting',
                    ]
                );

                $rule->lines()->updateOrCreate(
                    ['line_no' => 2],
                    [
                        'line_side' => 'credit',
                        'account_source_type' => 'mapping',
                        'mapping_key' => 'inventory.receipt.credit.grni',
                        'amount_source' => 'payload_total',
                        'description_template' => 'Inventory receipt GRNI posting',
                    ]
                );
            }
        });
    }
}
