<?php

use App\Models\AccountingPeriod;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;

it('renders balance sheet in 4 segments and applies carry-forward concept', function () {
    $user = User::factory()->create();

    $company = Company::create([
        'code' => 'CMP-BS',
        'name' => 'PT Balance Sheet',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'JKT',
        'name' => 'Jakarta',
        'is_active' => true,
    ]);

    Currency::firstOrCreate(['code' => 'IDR'], [
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'decimal_places' => 2,
        'is_active' => true,
    ]);

    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'year_label' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    $periodJan = AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 1,
        'period_name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => 'open',
    ]);

    $periodFeb = AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 2,
        'period_name' => 'February 2026',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'status' => 'open',
    ]);

    $assetGroup = AccountGroup::create(['company_id' => $company->id, 'code' => 'AST', 'name' => 'Asset', 'type' => 'asset']);
    $liabilityGroup = AccountGroup::create(['company_id' => $company->id, 'code' => 'LIA', 'name' => 'Liability', 'type' => 'liability']);
    $equityGroup = AccountGroup::create(['company_id' => $company->id, 'code' => 'EQT', 'name' => 'Equity', 'type' => 'equity']);
    $revenueGroup = AccountGroup::create(['company_id' => $company->id, 'code' => 'REV', 'name' => 'Revenue', 'type' => 'revenue']);
    $expenseGroup = AccountGroup::create(['company_id' => $company->id, 'code' => 'EXP', 'name' => 'Expense', 'type' => 'expense']);

    $createLeaf = function (string $codePrefix, string $namePrefix, string $accountType, string $normalBalance, int $accountGroupId) use ($company): ChartOfAccount {
        $level1 = ChartOfAccount::create([
            'company_id' => $company->id,
            'account_group_id' => $accountGroupId,
            'code' => $codePrefix.'00',
            'name' => $namePrefix.' L1',
            'level' => 1,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'financial_statement_group' => 'balance_sheet',
        ]);

        $level2 = ChartOfAccount::create([
            'company_id' => $company->id,
            'account_group_id' => $accountGroupId,
            'parent_id' => $level1->id,
            'code' => $codePrefix.'10',
            'name' => $namePrefix.' L2',
            'level' => 2,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'financial_statement_group' => 'balance_sheet',
        ]);

        $level3 = ChartOfAccount::create([
            'company_id' => $company->id,
            'account_group_id' => $accountGroupId,
            'parent_id' => $level2->id,
            'code' => $codePrefix.'20',
            'name' => $namePrefix.' L3',
            'level' => 3,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'financial_statement_group' => 'balance_sheet',
        ]);

        return ChartOfAccount::create([
            'company_id' => $company->id,
            'account_group_id' => $accountGroupId,
            'parent_id' => $level3->id,
            'code' => $codePrefix.'30',
            'name' => $namePrefix,
            'level' => 4,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'financial_statement_group' => in_array($accountType, ['revenue', 'expense'], true) ? 'profit_loss' : 'balance_sheet',
        ]);
    };


    $cash = $createLeaf('1', 'Cash', 'asset', 'debit', $assetGroup->id);
    $payable = $createLeaf('2', 'Accounts Payable', 'liability', 'credit', $liabilityGroup->id);
    $capital = $createLeaf('3', 'Capital', 'equity', 'credit', $equityGroup->id);
    $revenue = $createLeaf('4', 'Sales Revenue', 'revenue', 'credit', $revenueGroup->id);
    $expense = $createLeaf('5', 'Operational Expense', 'expense', 'debit', $expenseGroup->id);

    $openingEntry = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'OB-2026-001',
        'journal_type' => 'opening',
        'entry_date' => '2026-01-01',
        'posting_date' => '2026-01-01',
        'description' => 'Opening balance',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 1000,
        'total_credit' => 1000,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create(['journal_entry_id' => $openingEntry->id, 'line_no' => 1, 'account_id' => $cash->id, 'base_currency_debit' => 1000, 'base_currency_credit' => 0, 'debit' => 1000, 'credit' => 0, 'original_currency_code' => 'IDR', 'original_currency_amount' => 1000]);
    JournalLine::create(['journal_entry_id' => $openingEntry->id, 'line_no' => 2, 'account_id' => $payable->id, 'base_currency_debit' => 0, 'base_currency_credit' => 300, 'debit' => 0, 'credit' => 300, 'original_currency_code' => 'IDR', 'original_currency_amount' => 300]);
    JournalLine::create(['journal_entry_id' => $openingEntry->id, 'line_no' => 3, 'account_id' => $capital->id, 'base_currency_debit' => 0, 'base_currency_credit' => 700, 'debit' => 0, 'credit' => 700, 'original_currency_code' => 'IDR', 'original_currency_amount' => 700]);

    $saleEntry = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'JV-2026-001',
        'journal_type' => 'manual',
        'entry_date' => '2026-01-10',
        'posting_date' => '2026-01-10',
        'description' => 'Cash sale',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 500,
        'total_credit' => 500,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create(['journal_entry_id' => $saleEntry->id, 'line_no' => 1, 'account_id' => $cash->id, 'base_currency_debit' => 500, 'base_currency_credit' => 0, 'debit' => 500, 'credit' => 0, 'original_currency_code' => 'IDR', 'original_currency_amount' => 500]);
    JournalLine::create(['journal_entry_id' => $saleEntry->id, 'line_no' => 2, 'account_id' => $revenue->id, 'base_currency_debit' => 0, 'base_currency_credit' => 500, 'debit' => 0, 'credit' => 500, 'original_currency_code' => 'IDR', 'original_currency_amount' => 500]);

    $expenseEntry = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodFeb->id,
        'journal_no' => 'JV-2026-002',
        'journal_type' => 'manual',
        'entry_date' => '2026-02-10',
        'posting_date' => '2026-02-10',
        'description' => 'Cash expense',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 200,
        'total_credit' => 200,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create(['journal_entry_id' => $expenseEntry->id, 'line_no' => 1, 'account_id' => $expense->id, 'base_currency_debit' => 200, 'base_currency_credit' => 0, 'debit' => 200, 'credit' => 0, 'original_currency_code' => 'IDR', 'original_currency_amount' => 200]);
    JournalLine::create(['journal_entry_id' => $expenseEntry->id, 'line_no' => 2, 'account_id' => $cash->id, 'base_currency_debit' => 0, 'base_currency_credit' => 200, 'debit' => 0, 'credit' => 200, 'original_currency_code' => 'IDR', 'original_currency_amount' => 200]);

    $mtdResponse = $this->actingAs($user)->get(route('apps.reports.balance-sheet', [
        'type' => 'MTD',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'status' => 'posted',
        'year' => 2026,
        'period' => 2,
        'drill_level' => 1,
    ]), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk();

    $ytdResponse = $this->actingAs($user)->get(route('apps.reports.balance-sheet', [
        'type' => 'YTD',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'status' => 'posted',
        'year' => 2026,
        'period' => 2,
        'drill_level' => 1,
    ]), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk();

    expect($mtdResponse->json('props.summary.total_asset_current_year'))->toBe(1300.0)
        ->and($mtdResponse->json('props.summary.total_right_side_current_year'))->toBe(1300.0)
        ->and($mtdResponse->json('props.summary.current_year_profit_current_year'))->toBe(300.0)
        ->and($mtdResponse->json('props.rows'))->toHaveCount(4);

    expect($mtdResponse->json('props.summary.total_asset_current_year'))
        ->toBe($ytdResponse->json('props.summary.total_asset_current_year'))
        ->and($mtdResponse->json('props.summary.current_year_profit_current_year'))
        ->toBe($ytdResponse->json('props.summary.current_year_profit_current_year'));
});
