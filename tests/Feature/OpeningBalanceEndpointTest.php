<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;

it('creates opening balance journal with opening tag via dedicated endpoint', function () {
    $user = User::factory()->create();

    $company = Company::create([
        'code' => 'CMP-OB',
        'name' => 'PT Opening Balance',
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

    $coaLevel1 = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Assets',
        'level' => 1,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $coaLevel2 = ChartOfAccount::create([
        'company_id' => $company->id,
        'parent_id' => $coaLevel1->id,
        'code' => '1100',
        'name' => 'Current Assets',
        'level' => 2,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $coaLevel3 = ChartOfAccount::create([
        'company_id' => $company->id,
        'parent_id' => $coaLevel2->id,
        'code' => '1110',
        'name' => 'Cash',
        'level' => 3,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $cash = ChartOfAccount::create([
        'company_id' => $company->id,
        'parent_id' => $coaLevel3->id,
        'code' => '111001',
        'name' => 'Cash on Hand',
        'level' => 4,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $equity1 = ChartOfAccount::create([
        'company_id' => $company->id,
        'parent_id' => $coaLevel3->id,
        'code' => '111002',
        'name' => 'Temporary Equity',
        'level' => 4,
        'account_type' => 'equity',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('apps.opening-balances.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'journal_no' => 'OB-2026-001',
            'entry_date' => '2026-01-01',
            'posting_date' => '2026-01-01',
            'description' => 'Opening balance FY 2026',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'status' => 'posted',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'debit' => 1000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $equity1->id,
                    'debit' => 0,
                    'credit' => 1000,
                ],
            ],
        ]);

    $response->assertRedirect();

    $entry = JournalEntry::query()->where('journal_no', 'OB-2026-001')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->accounting_period_id)->toBe($periodJan->id)
        ->and($entry->journal_type)->toBe('opening')
        ->and($entry->source_module)->toBe('opening_balance_manual')
        ->and($entry->source_event)->toBe('manual_input')
        ->and($entry->source_document_type)->toBe('opening_balance');
});
