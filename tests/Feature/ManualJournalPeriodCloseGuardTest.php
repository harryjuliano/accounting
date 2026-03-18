<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\User;

function createPostingContext(string $fiscalStatus = 'open', string $periodStatus = 'open'): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-003',
        'name' => 'PT Guard',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    Currency::create([
        'code' => 'IDR',
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
        'status' => $fiscalStatus,
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 1,
        'period_name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => $periodStatus,
    ]);

    $cash = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Cash',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    $rev = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '4000',
        'name' => 'Revenue',
        'account_type' => 'revenue',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'income_statement',
    ]);

    return compact('user', 'company', 'cash', 'rev');
}

it('rejects posting when monthly period is soft closed', function () {
    $ctx = createPostingContext('open', 'soft_closed');

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.store'), [
            'company_id' => $ctx['company']->id,
            'journal_no' => 'MJ-2026-001',
            'entry_date' => '2026-01-15',
            'posting_date' => '2026-01-15',
            'description' => 'Test soft close guard',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'status' => 'posted',
            'lines' => [
                ['account_id' => $ctx['cash']->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $ctx['rev']->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

    $response->assertSessionHasErrors(['posting_date']);
});

it('rejects posting when fiscal year already hard closed', function () {
    $ctx = createPostingContext('closed', 'hard_closed');

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.store'), [
            'company_id' => $ctx['company']->id,
            'journal_no' => 'MJ-2026-002',
            'entry_date' => '2026-01-15',
            'posting_date' => '2026-01-15',
            'description' => 'Test hard close guard',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'status' => 'posted',
            'lines' => [
                ['account_id' => $ctx['cash']->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $ctx['rev']->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

    $response->assertSessionHasErrors(['posting_date']);
});
