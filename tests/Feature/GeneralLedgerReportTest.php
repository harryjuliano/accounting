<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;

it('uses posted entries by default and summarizes all rows across pages', function () {
    $user = User::factory()->create();

    $company = Company::create([
        'code' => 'CMP-GL',
        'name' => 'PT General Ledger',
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

    $coaLevel4 = ChartOfAccount::create([
        'company_id' => $company->id,
        'parent_id' => $coaLevel3->id,
        'code' => '111001',
        'name' => 'Cash on Hand',
        'level' => 4,
        'account_type' => 'assets',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    foreach (range(1, 25) as $index) {
        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'accounting_period_id' => $periodJan->id,
            'journal_no' => sprintf('JV-2026-%03d', $index),
            'journal_type' => 'manual',
            'entry_date' => '2026-01-10',
            'posting_date' => '2026-01-10',
            'description' => sprintf('Posted mutation %d', $index),
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'total_debit' => 10,
            'total_credit' => 0,
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'line_no' => 1,
            'account_id' => $coaLevel4->id,
            'base_currency_debit' => 10,
            'base_currency_credit' => 0,
            'debit' => 10,
            'credit' => 0,
            'original_currency_code' => 'IDR',
            'original_currency_amount' => 10,
        ]);
    }

    $draftEntry = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'JV-2026-DRF',
        'journal_type' => 'manual',
        'entry_date' => '2026-01-11',
        'posting_date' => '2026-01-11',
        'description' => 'Draft mutation',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 999,
        'total_credit' => 0,
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    JournalLine::create([
        'journal_entry_id' => $draftEntry->id,
        'line_no' => 1,
        'account_id' => $coaLevel4->id,
        'base_currency_debit' => 999,
        'base_currency_credit' => 0,
        'debit' => 999,
        'credit' => 0,
        'original_currency_code' => 'IDR',
        'original_currency_amount' => 999,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('apps.reports.general-ledger', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'coa_id' => $coaLevel4->id,
            'year' => 2026,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();

    expect($response->json('props.summary.total_debit'))->toBe(250.0)
        ->and($response->json('props.summary.total_credit'))->toBe(0.0)
        ->and($response->json('props.ledgerLines.data'))->toHaveCount(20)
        ->and($response->json('props.filters.status'))->toBe('posted');
});
