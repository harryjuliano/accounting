<?php

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('imports default transaction coa template for available level 3 parents', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-COA',
        'name' => 'PT COA',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $assetGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'AST',
        'name' => 'Assets',
        'type' => 'asset',
    ]);

    $expenseGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'EXP',
        'name' => 'Expense',
        'type' => 'expense',
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $assetGroup->id,
        'parent_id' => null,
        'code' => '1110',
        'name' => 'Cash on Hand',
        'alias_name' => 'Kas',
        'level' => 3,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'cashflow_group' => null,
        'allow_manual_posting' => false,
        'allow_reconciliation' => false,
        'requires_dimension' => false,
        'is_control_account' => false,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $expenseGroup->id,
        'parent_id' => null,
        'code' => '9110',
        'name' => 'Corporate Tax',
        'alias_name' => 'Pajak Penghasilan',
        'level' => 3,
        'account_type' => 'expense',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'income_statement',
        'cashflow_group' => null,
        'allow_manual_posting' => false,
        'allow_reconciliation' => false,
        'requires_dimension' => false,
        'is_control_account' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('apps.chart-of-accounts.import-default-template'), [
            'company_id' => $company->id,
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('chart_of_accounts', [
        'company_id' => $company->id,
        'code' => '1110-010',
        'name' => 'Petty Cash',
        'level' => 4,
        'parent_id' => ChartOfAccount::where('company_id', $company->id)->where('code', '1110')->value('id'),
    ]);

    $this->assertDatabaseHas('chart_of_accounts', [
        'company_id' => $company->id,
        'code' => '9110-010',
        'name' => 'Corporate Tax',
        'level' => 4,
    ]);

    $this->assertDatabaseMissing('chart_of_accounts', [
        'company_id' => $company->id,
        'code' => '1120-010',
    ]);
});

it('rejects default template import when level 4 coa already exists', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-EXIST',
        'name' => 'PT Existing',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $assetGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'AST',
        'name' => 'Assets',
        'type' => 'asset',
    ]);

    $parent = ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $assetGroup->id,
        'parent_id' => null,
        'code' => '1110',
        'name' => 'Cash on Hand',
        'alias_name' => 'Kas',
        'level' => 3,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'cashflow_group' => null,
        'allow_manual_posting' => false,
        'allow_reconciliation' => false,
        'requires_dimension' => false,
        'is_control_account' => false,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $assetGroup->id,
        'parent_id' => $parent->id,
        'code' => '1110-999',
        'name' => 'Existing L4',
        'alias_name' => 'Existing',
        'level' => 4,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'cashflow_group' => null,
        'allow_manual_posting' => true,
        'allow_reconciliation' => false,
        'requires_dimension' => false,
        'is_control_account' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('apps.chart-of-accounts.import-default-template'), [
            'company_id' => $company->id,
        ]);

    $response->assertSessionHasErrors('company_id');
});

it('exports then imports transaction coa template with the same format', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-EXPIMP',
        'name' => 'PT Export Import',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $assetGroup = AccountGroup::create([
        'company_id' => $company->id,
        'code' => 'AST',
        'name' => 'Assets',
        'type' => 'asset',
    ]);

    $parent = ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $assetGroup->id,
        'parent_id' => null,
        'code' => '1110',
        'name' => 'Cash on Hand',
        'alias_name' => 'Kas',
        'level' => 3,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'cashflow_group' => null,
        'allow_manual_posting' => false,
        'allow_reconciliation' => false,
        'requires_dimension' => false,
        'is_control_account' => false,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'account_group_id' => $assetGroup->id,
        'parent_id' => $parent->id,
        'code' => '1110-010',
        'name' => 'Petty Cash',
        'alias_name' => 'Kas Kecil',
        'level' => 4,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'cashflow_group' => null,
        'allow_manual_posting' => true,
        'allow_reconciliation' => false,
        'requires_dimension' => false,
        'is_control_account' => false,
        'is_active' => true,
    ]);

    $exportResponse = $this->actingAs($user)->get(route('apps.chart-of-accounts.export-transaction-template', [
        'company_id' => $company->id,
    ]));

    $exportResponse->assertOk();
    $exportContent = $exportResponse->streamedContent();
    expect($exportContent)->toContain('parent_code,code,name,alias_name,normal_balance,is_active,allow_manual_posting,allow_reconciliation,requires_dimension,is_control_account');

    ChartOfAccount::query()->where('company_id', $company->id)->where('level', 4)->delete();

    $file = UploadedFile::fake()->createWithContent('coa-template.csv', $exportContent);

    $importResponse = $this
        ->actingAs($user)
        ->post(route('apps.chart-of-accounts.import-transaction-template'), [
            'company_id' => $company->id,
            'file' => $file,
        ]);

    $importResponse->assertSessionHasNoErrors();

    $this->assertDatabaseHas('chart_of_accounts', [
        'company_id' => $company->id,
        'code' => '1110-010',
        'name' => 'Petty Cash',
        'level' => 4,
        'parent_id' => $parent->id,
    ]);
});
