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

it('renders trial balance with fiscal-year opening balance logic', function () {
    $user = User::factory()->create();

    $company = Company::create([
        'code' => 'CMP-TB',
        'name' => 'PT Trial Balance',
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

    $entryPrevYear = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'JV-2025-001',
        'journal_type' => 'opening',
        'entry_date' => '2025-12-31',
        'posting_date' => '2025-12-31',
        'description' => 'Legacy opening carry forward',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 100,
        'total_credit' => 0,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create([
        'journal_entry_id' => $entryPrevYear->id,
        'line_no' => 1,
        'account_id' => $coaLevel4->id,
        'base_currency_debit' => 100,
        'base_currency_credit' => 0,
        'debit' => 100,
        'credit' => 0,
        'original_currency_code' => 'IDR',
        'original_currency_amount' => 100,
    ]);

    $entryOpeningCurrentYear = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'OB-2026-001',
        'journal_type' => 'opening',
        'entry_date' => '2026-01-01',
        'posting_date' => '2026-01-01',
        'description' => 'Opening balance 2026',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 60,
        'total_credit' => 0,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create([
        'journal_entry_id' => $entryOpeningCurrentYear->id,
        'line_no' => 1,
        'account_id' => $coaLevel4->id,
        'base_currency_debit' => 60,
        'base_currency_credit' => 0,
        'debit' => 60,
        'credit' => 0,
        'original_currency_code' => 'IDR',
        'original_currency_amount' => 60,
    ]);

    $entryJan = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'JV-2026-001',
        'journal_type' => 'manual',
        'entry_date' => '2026-01-10',
        'posting_date' => '2026-01-10',
        'description' => 'January mutation',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 50,
        'total_credit' => 0,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create([
        'journal_entry_id' => $entryJan->id,
        'line_no' => 1,
        'account_id' => $coaLevel4->id,
        'base_currency_debit' => 50,
        'base_currency_credit' => 0,
        'debit' => 50,
        'credit' => 0,
        'original_currency_code' => 'IDR',
        'original_currency_amount' => 50,
    ]);

    $entryFeb = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodFeb->id,
        'journal_no' => 'JV-2026-002',
        'journal_type' => 'manual',
        'entry_date' => '2026-02-12',
        'posting_date' => '2026-02-12',
        'description' => 'February mutation',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 30,
        'total_credit' => 0,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    JournalLine::create([
        'journal_entry_id' => $entryFeb->id,
        'line_no' => 1,
        'account_id' => $coaLevel4->id,
        'base_currency_debit' => 30,
        'base_currency_credit' => 0,
        'debit' => 30,
        'credit' => 0,
        'original_currency_code' => 'IDR',
        'original_currency_amount' => 30,
    ]);

    $ytdResponse = $this
        ->actingAs($user)
        ->get(route('apps.reports.trial-balance', [
            'type' => 'YTD',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'posted',
            'year' => 2026,
            'period' => 2,
        ]), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();

    $ytdRows = $ytdResponse->json('props.rows');
    $ytdSummary = $ytdResponse->json('props.summary');

    expect($ytdRows)->toHaveCount(1)
        ->and($ytdRows[0]['opening_balance'])->toBe(60.0)
        ->and($ytdRows[0]['mutation_debit'])->toBe(80.0)
        ->and($ytdRows[0]['closing_balance'])->toBe(140.0)
        ->and($ytdSummary['opening_balance'])->toBe(60.0)
        ->and($ytdSummary['mutation_debit'])->toBe(80.0)
        ->and($ytdSummary['mutation_credit'])->toBe(0.0)
        ->and($ytdSummary['closing_balance'])->toBe(140.0);

    $mtdResponse = $this
        ->actingAs($user)
        ->get(route('apps.reports.trial-balance', [
            'type' => 'MTD',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'posted',
            'year' => 2026,
            'period' => 2,
        ]), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();

    $mtdRows = $mtdResponse->json('props.rows');
    $mtdSummary = $mtdResponse->json('props.summary');

    expect($mtdRows)->toHaveCount(1)
        ->and($mtdRows[0]['opening_balance'])->toBe(110.0)
        ->and($mtdRows[0]['mutation_debit'])->toBe(30.0)
        ->and($mtdRows[0]['closing_balance'])->toBe(140.0)
        ->and($mtdSummary['opening_balance'])->toBe(110.0)
        ->and($mtdSummary['mutation_debit'])->toBe(30.0)
        ->and($mtdSummary['mutation_credit'])->toBe(0.0)
        ->and($mtdSummary['closing_balance'])->toBe(140.0);
});
