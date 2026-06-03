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
            'source_module' => 'sales',
            'source_module_name' => 'Modul Penjualan',
            'source_event' => 'sales_invoice_posted',
            'source_document_no' => sprintf('SI-2026-%03d', $index),
            'counterparty_type' => 'customer',
            'counterparty_code' => 'CUST-001',
            'counterparty_name' => 'Customer A',
            'salesperson_code' => 'SLS-001',
            'salesperson_name' => 'Budi Sales',
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
            'item_code' => 'BRG-001',
            'item_name' => 'Barang Contoh',
            'quantity' => 10,
            'quantity_uom' => 'PCS',
            'dimension_details_json' => [
                'cost_center' => [
                    'code' => 'CJR-ARTHA',
                    'name' => 'CJR Artha',
                ],
            ],
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
        ->and($response->json('props.ledgerLines.data.0.document_no'))->toBe('SI-2026-001')
        ->and($response->json('props.ledgerLines.data.0.source_module'))->toBe('sales')
        ->and($response->json('props.ledgerLines.data.0.source_module_name'))->toBe('Modul Penjualan')
        ->and($response->json('props.ledgerLines.data.0.counterparty_code'))->toBe('CUST-001')
        ->and($response->json('props.ledgerLines.data.0.counterparty_name'))->toBe('Customer A')
        ->and($response->json('props.ledgerLines.data.0.salesperson_code'))->toBe('SLS-001')
        ->and($response->json('props.ledgerLines.data.0.salesperson_name'))->toBe('Budi Sales')
        ->and($response->json('props.ledgerLines.data.0.coa_code'))->toBe('111001')
        ->and($response->json('props.ledgerLines.data.0.coa_name'))->toBe('Cash on Hand')
        ->and($response->json('props.ledgerLines.data.0.cost_center_code'))->toBe('CJR-ARTHA')
        ->and($response->json('props.ledgerLines.data.0.cost_center_name'))->toBe('CJR Artha')
        ->and($response->json('props.ledgerLines.data.0.item_code'))->toBe('BRG-001')
        ->and($response->json('props.ledgerLines.data.0.item_name'))->toBe('Barang Contoh')
        ->and($response->json('props.ledgerLines.data.0.quantity'))->toBe(10.0)
        ->and($response->json('props.ledgerLines.data.0.quantity_uom'))->toBe('PCS')
        ->and($response->json('props.filters.status'))->toBe('posted');
});

it('defaults to the latest available posting year so year filter stays in sync with date range', function () {
    $user = User::factory()->create();

    $company = Company::create([
        'code' => 'CMP-GL-2',
        'name' => 'PT General Ledger 2',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'SBY',
        'name' => 'Surabaya',
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
        'year_label' => '2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    $periodJan = AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 1,
        'period_name' => 'January 2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
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

    $entry = JournalEntry::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'accounting_period_id' => $periodJan->id,
        'journal_no' => 'JV-2025-001',
        'journal_type' => 'manual',
        'entry_date' => '2025-01-10',
        'posting_date' => '2025-01-10',
        'description' => 'Posted mutation 2025',
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

    $response = $this
        ->actingAs($user)
        ->get(route('apps.reports.general-ledger', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'coa_id' => $coaLevel4->id,
        ]), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();

    expect($response->json('props.filters.year'))->toBe(2025)
        ->and($response->json('props.filters.date_from'))->toBe('2025-01-01')
        ->and($response->json('props.filters.date_to'))->toBe('2025-12-31')
        ->and($response->json('props.yearOptions.0'))->toBe(2025);
});
