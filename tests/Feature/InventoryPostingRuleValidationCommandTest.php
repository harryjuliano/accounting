<?php

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\IntegrationEvent;
use App\Models\PostingRule;

function createPostingRuleContext(): array
{
    $company = Company::create([
        'code' => 'CMP-RULE',
        'name' => 'PT Rule Engine',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $inventoryAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1301',
        'name' => 'Inventory Asset',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $grniAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '2105',
        'name' => 'GRNI',
        'account_type' => 'liability',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    \DB::table('coa_mappings')->insert([
        [
            'company_id' => $company->id,
            'mapping_key' => 'inventory.receipt.purchase.debit.inventory',
            'account_id' => $inventoryAccount->id,
            'module_name' => 'inventory',
            'description' => 'Inventory Asset account',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company->id,
            'mapping_key' => 'inventory.receipt.purchase.credit.grni',
            'account_id' => $grniAccount->id,
            'module_name' => 'inventory',
            'description' => 'GRNI account',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $rule = PostingRule::create([
        'company_id' => $company->id,
        'module_name' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'transaction_type' => 'inventory.receipt.purchase',
        'rule_code' => 'INV_RECEIPT_PURCHASE',
        'rule_name' => 'Inventory receipt purchase',
        'version' => 1,
        'effective_from' => '2026-01-01',
        'priority' => 100,
        'is_active' => true,
    ]);

    $rule->lines()->create([
        'line_no' => 1,
        'line_side' => 'debit',
        'account_source_type' => 'mapping',
        'mapping_key' => 'inventory.receipt.purchase.debit.inventory',
        'amount_source' => 'payload_total',
    ]);

    $rule->lines()->create([
        'line_no' => 2,
        'line_side' => 'credit',
        'account_source_type' => 'mapping',
        'mapping_key' => 'inventory.receipt.purchase.credit.grni',
        'amount_source' => 'payload_total',
    ]);

    return compact('company');
}

it('validates received inventory events using posting rules command', function () {
    $ctx = createPostingRuleContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-VALIDATE-1001',
        'payload_json' => [
            'transaction_type' => 'inventory.receipt.purchase',
            'amounts' => ['total' => 100000],
            'currency_code' => 'IDR',
        ],
        'event_datetime' => '2026-03-28 10:00:00',
        'processing_status' => 'received',
    ]);

    $this->artisan('integration:inventory:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated');
    expect(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(100000.0);
    expect(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(100000.0);
});

it('validates received inventory events using total_amount payload field', function () {
    $ctx = createPostingRuleContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-VALIDATE-1002',
        'payload_json' => [
            'transaction_type' => 'inventory.receipt.purchase',
            'total_amount' => 500000,
            'currency_code' => 'IDR',
            'lines' => [
                [
                    'item_code' => 'SKU-TEST',
                    'qty' => 20,
                    'unit_cost' => 25000,
                ],
            ],
        ],
        'event_datetime' => '2026-03-28 10:00:00',
        'processing_status' => 'received',
    ]);

    $this->artisan('integration:inventory:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated');
    expect(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(500000.0);
    expect(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(500000.0);
});

it('marks event failed when posting rule is missing', function () {
    $company = Company::create([
        'code' => 'CMP-NORULE',
        'name' => 'PT No Rule',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $event = IntegrationEvent::create([
        'company_id' => $company->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.adjustment.posted',
        'idempotency_key' => 'INV-FAIL-1001',
        'payload_json' => [
            'amounts' => ['total' => 25000],
        ],
        'event_datetime' => '2026-03-28 10:00:00',
        'processing_status' => 'received',
    ]);

    $this->artisan('integration:inventory:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('failed');
    expect($event->error_message)->toBe('posting_rule_not_found');

    $this->assertDatabaseHas('integration_failures', [
        'integration_event_id' => $event->id,
        'failure_stage' => 'validation',
        'error_code' => 'posting_rule_not_found',
    ]);

    $this->assertDatabaseHas('integration_event_logs', [
        'integration_event_id' => $event->id,
        'level' => 'error',
    ]);
});

it('validates purchase return receipt using separate transaction type and mappings', function () {
    $company = Company::create([
        'code' => 'CMP-RET-RULE',
        'name' => 'PT Return Rule Engine',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $inventoryAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1301',
        'name' => 'Inventory Asset',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $returnClearingAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '2190',
        'name' => 'Purchase Return Clearing',
        'account_type' => 'liability',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    \DB::table('coa_mappings')->insert([
        [
            'company_id' => $company->id,
            'mapping_key' => 'inventory.receipt.purchase_return.debit.inventory',
            'account_id' => $inventoryAccount->id,
            'module_name' => 'inventory',
            'description' => 'Inventory account for purchase return receipts',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company->id,
            'mapping_key' => 'inventory.receipt.purchase_return.credit.clearing',
            'account_id' => $returnClearingAccount->id,
            'module_name' => 'inventory',
            'description' => 'Purchase return clearing account',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $rule = PostingRule::create([
        'company_id' => $company->id,
        'module_name' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'transaction_type' => 'inventory.receipt.purchase_return',
        'rule_code' => 'INV_RECEIPT_PURCHASE_RETURN',
        'rule_name' => 'Inventory receipt purchase return',
        'version' => 1,
        'effective_from' => '2026-01-01',
        'priority' => 100,
        'is_active' => true,
    ]);

    $rule->lines()->create([
        'line_no' => 1,
        'line_side' => 'debit',
        'account_source_type' => 'mapping',
        'mapping_key' => 'inventory.receipt.purchase_return.debit.inventory',
        'amount_source' => 'payload_total',
    ]);

    $rule->lines()->create([
        'line_no' => 2,
        'line_side' => 'credit',
        'account_source_type' => 'mapping',
        'mapping_key' => 'inventory.receipt.purchase_return.credit.clearing',
        'amount_source' => 'payload_total',
    ]);

    $event = IntegrationEvent::create([
        'company_id' => $company->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-VALIDATE-RETURN-1001',
        'payload_json' => [
            'transaction_type' => 'inventory.receipt.purchase_return',
            'total_amount' => 250000,
            'currency_code' => 'IDR',
            'lines' => [
                [
                    'item_code' => 'SKU-RET-TEST',
                    'qty' => 10,
                    'unit_cost' => 25000,
                ],
            ],
        ],
        'event_datetime' => '2026-03-28 10:15:00',
        'processing_status' => 'received',
    ]);

    $this->artisan('integration:inventory:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated');
    expect(data_get($event->payload_json, '_posting_rule.rule_code'))->toBe('INV_RECEIPT_PURCHASE_RETURN');
    expect(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(250000.0);
    expect(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(250000.0);
    expect(data_get($event->payload_json, '_posting_preview.lines.1.account_id'))->toBe($returnClearingAccount->id);
});
