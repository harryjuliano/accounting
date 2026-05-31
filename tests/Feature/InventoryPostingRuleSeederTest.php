<?php

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Database\Seeders\InventoryPostingRuleSeeder;

it('seeds inventory receipt and issue posting rules', function () {
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

    $expenseGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'EXP',
        'name' => 'Expenses',
        'type' => 'expense',
    ]);

    foreach ([
        ['5120', 'Cost of Goods Sold'],
        ['7100', 'Operating Expenses'],
        ['8100', 'Other Income/Expenses'],
    ] as [$code, $name]) {
        ChartOfAccount::create([
            'company_id' => $company->id,
            'account_group_id' => $expenseGroup->id,
            'code' => $code,
            'name' => $name,
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'financial_statement_group' => 'income_statement',
            'is_active' => true,
        ]);
    }

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

    foreach ([
        'INV_ISSUE_SALES' => 'inventory.issue.sales',
        'INV_ISSUE_DAMAGED' => 'inventory.issue.damaged',
        'INV_ISSUE_SAMPLE' => 'inventory.issue.sample',
        'INV_ISSUE_INTERNAL_USE' => 'inventory.issue.internal_use',
    ] as $ruleCode => $transactionType) {
        $this->assertDatabaseHas('posting_rules', [
            'company_id' => $company->id,
            'rule_code' => $ruleCode,
            'event_name' => 'inventory.issue.posted',
            'transaction_type' => $transactionType,
            'is_active' => true,
        ]);
    }

    $this->assertDatabaseHas('posting_rule_lines', [
        'line_no' => 1,
        'line_side' => 'debit',
        'mapping_key' => 'inventory.issue.sales.debit.cogs',
        'amount_source' => 'payload_total',
    ]);

    $this->assertDatabaseHas('posting_rule_lines', [
        'line_no' => 2,
        'line_side' => 'credit',
        'mapping_key' => 'inventory.issue.internal_use.credit.inventory',
        'amount_source' => 'payload_total',
    ]);

    $this->assertDatabaseHas('coa_mappings', [
        'company_id' => $company->id,
        'module_name' => 'inventory',
        'mapping_key' => 'inventory.issue.sample.debit.promotion',
    ]);
});
