<?php

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Database\Seeders\InventoryPostingRuleSeeder;

it('seeds purchase and purchase return inventory receipt posting rules', function () {
    $company = Company::create([
        'code' => 'CMP-SEED-RULE',
        'name' => 'PT Seed Rule',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $assetGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'AST',
        'name' => 'Assets',
        'type' => 'asset',
    ]);

    $liabilityGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'LIA',
        'name' => 'Liabilities',
        'type' => 'liability',
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $assetGroup->id,
        'code' => '1150',
        'name' => 'Inventory',
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $liabilityGroup->id,
        'code' => '2110',
        'name' => 'Accounts Payable',
        'account_type' => 'liabilities',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $liabilityGroup->id,
        'code' => '2120',
        'name' => 'Accrued Expenses',
        'account_type' => 'liabilities',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    $this->seed(InventoryPostingRuleSeeder::class);

    $this->assertDatabaseHas('posting_rules', [
        'company_id' => $company->id,
        'rule_code' => 'INV_RECEIPT_PURCHASE',
        'event_name' => 'inventory.receipt.posted',
        'transaction_type' => 'inventory.receipt.purchase',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('posting_rules', [
        'company_id' => $company->id,
        'rule_code' => 'INV_RECEIPT_PURCHASE_RETURN',
        'event_name' => 'inventory.receipt.posted',
        'transaction_type' => 'inventory.receipt.purchase_return',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('posting_rule_lines', [
        'line_no' => 1,
        'line_side' => 'debit',
        'mapping_key' => 'inventory.receipt.purchase.debit.inventory',
        'amount_source' => 'payload_total',
    ]);

    $this->assertDatabaseHas('posting_rule_lines', [
        'line_no' => 2,
        'line_side' => 'credit',
        'mapping_key' => 'inventory.receipt.purchase_return.credit.clearing',
        'amount_source' => 'payload_total',
    ]);

    $this->assertDatabaseHas('coa_mappings', [
        'company_id' => $company->id,
        'module_name' => 'inventory',
        'mapping_key' => 'inventory.receipt.purchase.credit.grni',
    ]);

    $this->assertDatabaseHas('coa_mappings', [
        'company_id' => $company->id,
        'module_name' => 'inventory',
        'mapping_key' => 'inventory.receipt.purchase_return.credit.clearing',
    ]);
});
