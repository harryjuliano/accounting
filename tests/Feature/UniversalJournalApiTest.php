<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationClientCredential;
use App\Models\JournalEntry;
use App\Models\User;

function createUniversalJournalApiContext(): array
{
    $company = Company::create([
        'code' => 'CMP-UNI',
        'name' => 'PT Universal Journal',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    Currency::firstOrCreate(['code' => 'IDR'], [
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'decimal_places' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'CJR-ARTHA',
        'name' => 'CJR Artha',
        'is_active' => true,
    ]);

    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'year_label' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 2,
        'period_name' => 'February 2026',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'status' => 'open',
    ]);

    $cashAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1101',
        'name' => 'Cash in Bank',
        'level' => 4,
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    $revenueAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '4101',
        'name' => 'Sales Revenue',
        'level' => 4,
        'account_type' => 'revenue',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'profit_loss',
        'is_active' => true,
    ]);

    $headerAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1100',
        'name' => 'Current Assets Header',
        'level' => 2,
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
        'allow_manual_posting' => false,
    ]);

    $clientSecret = 'universal-secret-123';
    $credential = IntegrationClientCredential::create([
        'client_key' => 'UNIVERSAL-SALES-KEY',
        'client_secret_hash' => hash('sha256', $clientSecret),
        'source_module' => 'sales',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'client_name' => 'Universal Sales Module',
        'is_active' => true,
    ]);

    return compact('company', 'user', 'branch', 'cashAccount', 'revenueAccount', 'headerAccount', 'credential', 'clientSecret');
}

it('posts a universal journal directly from module payload', function () {
    $ctx = createUniversalJournalApiContext();

    $response = $this->postJson(route('api.integrations.universal-journals.store'), [
        'client_key' => $ctx['credential']->client_key,
        'client_secret' => $ctx['clientSecret'],
        'company_code' => $ctx['company']->code,
        'branch_code' => $ctx['branch']->code,
        'integration_key' => 'sales_invoice:SI-2026-0001:posted',
        'journal_no' => 'UNI-JRN-0001',
        'source_module' => 'sales',
        'source_module_name' => 'Modul Penjualan',
        'source_event' => 'sales_invoice_posted',
        'source_document_type' => 'sales_invoice',
        'source_document_id' => 'SI-1',
        'source_document_no' => 'SI-2026-0001',
        'entry_date' => '2026-02-01',
        'posting_date' => '2026-02-01',
        'reference_no' => 'PO-CUST-001',
        'description' => 'Penjualan Barang Contoh',
        'counterparty_type' => 'customer',
        'counterparty_code' => 'CUST-001',
        'counterparty_name' => 'Customer A',
        'salesperson_code' => 'SLS-001',
        'salesperson_name' => 'Budi Sales',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'lines' => [
            [
                'line_no' => 1,
                'account_code' => '1101',
                'description' => 'Kas masuk Customer A',
                'debit' => 1000000,
                'credit' => 0,
                'item_code' => 'BRG-001',
                'item_name' => 'Barang Contoh',
                'quantity' => 10,
                'quantity_uom' => 'PCS',
                'cost_center_code' => 'CJR-ARTHA',
                'cost_center_name' => 'CJR Artha',
            ],
            [
                'line_no' => 2,
                'account_id' => $ctx['revenueAccount']->id,
                'account_code' => '4101',
                'description' => 'Pendapatan Barang Contoh',
                'debit' => 0,
                'credit' => 1000000,
                'item_code' => 'BRG-001',
                'item_name' => 'Barang Contoh',
                'quantity' => 10,
                'quantity_uom' => 'PCS',
                'cost_center_code' => 'CJR-ARTHA',
                'cost_center_name' => 'CJR Artha',
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.is_duplicate', false)
        ->assertJsonPath('data.journal_no', 'UNI-JRN-0001')
        ->assertJsonPath('data.total_debit', 1000000.0)
        ->assertJsonPath('data.total_credit', 1000000.0);

    $journal = JournalEntry::query()->where('integration_key', 'sales_invoice:SI-2026-0001:posted')->firstOrFail();
    $line = $journal->lines()->orderBy('line_no')->firstOrFail();

    expect($journal->company_id)->toBe($ctx['company']->id)
        ->and($journal->branch_id)->toBe($ctx['branch']->id)
        ->and($journal->source_module)->toBe('sales')
        ->and($journal->source_module_name)->toBe('Modul Penjualan')
        ->and($journal->source_event)->toBe('sales_invoice_posted')
        ->and($journal->source_document_no)->toBe('SI-2026-0001')
        ->and($journal->counterparty_type)->toBe('customer')
        ->and($journal->counterparty_code)->toBe('CUST-001')
        ->and($journal->counterparty_name)->toBe('Customer A')
        ->and($journal->salesperson_code)->toBe('SLS-001')
        ->and($journal->salesperson_name)->toBe('Budi Sales')
        ->and($journal->status)->toBe('posted')
        ->and($journal->posted_at)->not->toBeNull()
        ->and($line->account_id)->toBe($ctx['cashAccount']->id)
        ->and($line->item_code)->toBe('BRG-001')
        ->and($line->item_name)->toBe('Barang Contoh')
        ->and((float) $line->quantity)->toBe(10.0)
        ->and($line->quantity_uom)->toBe('PCS')
        ->and($line->dimension_details_json)->toBe([
            'cost_center' => [
                'code' => 'CJR-ARTHA',
                'name' => 'CJR Artha',
            ],
        ]);
});

it('reuses existing journal for duplicate universal integration keys', function () {
    $ctx = createUniversalJournalApiContext();

    $payload = [
        'client_key' => $ctx['credential']->client_key,
        'client_secret' => $ctx['clientSecret'],
        'integration_key' => 'sales_invoice:SI-2026-0002:posted',
        'journal_no' => 'UNI-JRN-0002',
        'source_module' => 'sales',
        'entry_date' => '2026-02-02',
        'posting_date' => '2026-02-02',
        'description' => 'Duplicate safe journal',
        'currency_code' => 'IDR',
        'lines' => [
            ['line_no' => 1, 'account_code' => '1101', 'debit' => 500000, 'credit' => 0],
            ['line_no' => 2, 'account_code' => '4101', 'debit' => 0, 'credit' => 500000],
        ],
    ];

    $this->postJson(route('api.integrations.universal-journals.store'), $payload)->assertCreated();

    $this->postJson(route('api.integrations.universal-journals.store'), $payload)
        ->assertOk()
        ->assertJsonPath('data.is_duplicate', true)
        ->assertJsonPath('data.journal_no', 'UNI-JRN-0002');

    expect(JournalEntry::query()->where('integration_key', 'sales_invoice:SI-2026-0002:posted')->count())->toBe(1);
});

it('rejects unbalanced universal journal payloads', function () {
    $ctx = createUniversalJournalApiContext();

    $this->postJson(route('api.integrations.universal-journals.store'), [
        'client_key' => $ctx['credential']->client_key,
        'client_secret' => $ctx['clientSecret'],
        'integration_key' => 'sales_invoice:SI-2026-0003:posted',
        'source_module' => 'sales',
        'entry_date' => '2026-02-03',
        'posting_date' => '2026-02-03',
        'description' => 'Unbalanced journal',
        'currency_code' => 'IDR',
        'lines' => [
            ['line_no' => 1, 'account_code' => '1101', 'debit' => 500000, 'credit' => 0],
            ['line_no' => 2, 'account_code' => '4101', 'debit' => 0, 'credit' => 400000],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('lines');
});


it('rejects universal journal lines posted to non-postable COA header accounts', function () {
    $ctx = createUniversalJournalApiContext();

    $this->postJson(route('api.integrations.universal-journals.store'), [
        'client_key' => $ctx['credential']->client_key,
        'client_secret' => $ctx['clientSecret'],
        'integration_key' => 'sales_invoice:SI-2026-0004:posted',
        'source_module' => 'sales',
        'entry_date' => '2026-02-04',
        'posting_date' => '2026-02-04',
        'description' => 'Header account should fail',
        'currency_code' => 'IDR',
        'lines' => [
            ['line_no' => 1, 'account_code' => '1100', 'debit' => 500000, 'credit' => 0],
            ['line_no' => 2, 'account_code' => '4101', 'debit' => 0, 'credit' => 500000],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('lines.0.account_code');

    expect(JournalEntry::query()->where('integration_key', 'sales_invoice:SI-2026-0004:posted')->exists())->toBeFalse();
});
