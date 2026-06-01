<?php

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Database\Seeders\SalesInvoicePostingRuleSeeder;

it('seeds sales invoice posting rule accounts and mappings used by module preset payload examples', function () {
    $company = Company::create([
        'code' => 'CMP-SALES-SEED',
        'name' => 'PT Sales Seed',
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

    $revenueGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'REV',
        'name' => 'Revenue',
        'type' => 'revenue',
    ]);

    $expenseGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'EXP',
        'name' => 'Expense',
        'type' => 'expense',
    ]);

    $revenueParent = ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $revenueGroup->id,
        'code' => '4100',
        'name' => 'Revenue',
        'account_type' => 'revenue',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'income_statement',
        'is_active' => true,
    ]);

    foreach ([
        [$assetGroup->id, '1130', 'Accounts Receivable', 'asset', 'debit', 'balance_sheet', null],
        [$assetGroup->id, '1150', 'Inventory', 'asset', 'debit', 'balance_sheet', null],
        [$liabilityGroup->id, '2130', 'Taxes Payable', 'liability', 'credit', 'balance_sheet', null],
        [$revenueGroup->id, '4120', 'Trading Revenue', 'revenue', 'credit', 'income_statement', $revenueParent->id],
        [$expenseGroup->id, '5120', 'Cost of Goods Sold', 'expense', 'debit', 'income_statement', null],
    ] as [$groupId, $code, $name, $type, $normalBalance, $statementGroup, $parentId]) {
        ChartOfAccount::create([
            'company_id' => $company->id,
            'account_group_id' => $groupId,
            'parent_id' => $parentId,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $normalBalance,
            'financial_statement_group' => $statementGroup,
            'is_active' => true,
        ]);
    }

    $this->seed(SalesInvoicePostingRuleSeeder::class);

    $this->assertDatabaseHas('chart_of_accounts', [
        'company_id' => $company->id,
        'code' => '4130',
        'name' => 'Sales Discount',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('chart_of_accounts', [
        'company_id' => $company->id,
        'code' => '4140',
        'name' => 'Freight Income',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $discountAccountId = ChartOfAccount::query()
        ->where('company_id', $company->id)
        ->where('code', '4130')
        ->value('id');

    $freightAccountId = ChartOfAccount::query()
        ->where('company_id', $company->id)
        ->where('code', '4140')
        ->value('id');

    $this->assertDatabaseHas('coa_mappings', [
        'company_id' => $company->id,
        'module_name' => 'sales',
        'mapping_key' => 'sales.invoice.debit.discount',
        'account_id' => $discountAccountId,
    ]);

    $this->assertDatabaseHas('coa_mappings', [
        'company_id' => $company->id,
        'module_name' => 'sales',
        'mapping_key' => 'sales.invoice.credit.freight_income',
        'account_id' => $freightAccountId,
    ]);
});
