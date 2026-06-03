<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function createManualJournalImportContext(): array
{
    $company = Company::create([
        'code' => 'CMP-IMP',
        'name' => 'PT Import Test',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
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
        'year_label' => '2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 1,
        'period_name' => 'January 2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'status' => 'open',
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1101',
        'name' => 'Kas',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
    ]);

    ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '4101',
        'name' => 'Pendapatan',
        'account_type' => 'revenue',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'income_statement',
    ]);

    return compact('user', 'company');
}

it('imports manual journals from semicolon csv with DD/MM/YYYY dates', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        'journal_no;entry_date;posting_date;reference_no;description;currency_code;exchange_rate;status;branch_code;account_code;line_description;debit;credit',
        'JRN-010125;01/01/2025;01/01/2025;SIS-001;Saldo Awal Piutang 2025;IDR;1;posted;;1101;Piutang Usaha;1000;0',
        'JRN-010125;01/01/2025;01/01/2025;SIS-001;Saldo Awal Piutang 2025;IDR;1;posted;;4101;Piutang Usaha;0;1000',
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-import.csv', $csv);

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('journal_entries', [
        'company_id' => $ctx['company']->id,
        'journal_no' => 'JRN-010125',
        'entry_date' => '2025-01-01',
        'posting_date' => '2025-01-01',
        'status' => 'posted',
    ]);

    $journal = JournalEntry::query()->where('journal_no', 'JRN-010125')->firstOrFail();
    expect($journal->lines()->count())->toBe(2);
});

it('returns success message with posting period filter hint after import', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        'journal_no,entry_date,posting_date,reference_no,description,currency_code,exchange_rate,status,branch_code,account_code,line_description,debit,credit',
        'JRN-020125,2025-01-02,2025-01-02,SIS-002,Saldo Awal Piutang 2025,IDR,1,posted,,1101,Piutang Usaha,2500,0',
        'JRN-020125,2025-01-02,2025-01-02,SIS-002,Saldo Awal Piutang 2025,IDR,1,posted,,4101,Piutang Usaha,0,2500',
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-import.csv', $csv);

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'sesuaikan filter Tahun/Bulan'));
});

it('imports edited excel csv format with non-padded date and scientific number', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        'journal_no,entry_date,posting_date,reference_no,description,currency_code,exchange_rate,status,branch_code,account_code,line_description,debit,credit',
        'JRN-030125,1/1/2025,1/1/2025,SIS-003,Saldo Awal Piutang 2025,IDR,1,posted,,1101,Piutang Usaha,1.066E+09,0',
        'JRN-030125,1/1/2025,1/1/2025,SIS-003,Saldo Awal Piutang 2025,IDR,1,posted,,4101,Piutang Usaha,0,1066000000',
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-import-edited.csv', $csv);

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $journal = JournalEntry::query()->where('journal_no', 'JRN-030125')->firstOrFail();
    expect($journal->lines()->count())->toBe(2);
    expect((float) $journal->lines()->sum('debit'))->toBe(1066000000.0);
    expect((float) $journal->lines()->sum('credit'))->toBe(1066000000.0);
});

it('imports universal header and item fields from expanded manual journal csv', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        'journal_no,entry_date,posting_date,reference_no,description,currency_code,exchange_rate,status,branch_code,source_module,source_module_name,source_event,counterparty_type,counterparty_code,counterparty_name,salesperson_code,salesperson_name,account_code,line_description,item_code,item_name,quantity,quantity_uom,debit,credit',
        'JRN-040125,2025-01-04,2025-01-04,SI-004,Penjualan Barang,IDR,1,posted,,sales,Modul Penjualan,sales_invoice_posted,customer,CUST-001,Customer A,SLS-001,Budi Sales,1101,Kas Masuk,BRG-001,Barang Contoh,10,PCS,1000,0',
        'JRN-040125,2025-01-04,2025-01-04,SI-004,Penjualan Barang,IDR,1,posted,,sales,Modul Penjualan,sales_invoice_posted,customer,CUST-001,Customer A,SLS-001,Budi Sales,4101,Pendapatan,BRG-001,Barang Contoh,10,PCS,0,1000',
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-import-universal.csv', $csv);

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $journal = JournalEntry::query()->where('journal_no', 'JRN-040125')->firstOrFail();
    $line = $journal->lines()->orderBy('line_no')->firstOrFail();

    expect($journal->source_module)->toBe('sales')
        ->and($journal->source_module_name)->toBe('Modul Penjualan')
        ->and($journal->source_event)->toBe('sales_invoice_posted')
        ->and($journal->counterparty_type)->toBe('customer')
        ->and($journal->counterparty_code)->toBe('CUST-001')
        ->and($journal->counterparty_name)->toBe('Customer A')
        ->and($journal->salesperson_code)->toBe('SLS-001')
        ->and($journal->salesperson_name)->toBe('Budi Sales')
        ->and($line->item_code)->toBe('BRG-001')
        ->and($line->item_name)->toBe('Barang Contoh')
        ->and((float) $line->quantity)->toBe(10.0)
        ->and($line->quantity_uom)->toBe('PCS');
});

it('imports cost center columns from expanded manual journal csv into dimension details', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        'journal_no,entry_date,posting_date,reference_no,description,currency_code,exchange_rate,status,branch_code,source_module,source_module_name,source_event,counterparty_type,counterparty_code,counterparty_name,salesperson_code,salesperson_name,account_code,line_description,item_code,item_name,quantity,quantity_uom,cost_center_code,cost_center_name,debit,credit',
        'JRN-050125,2025-01-05,2025-01-05,SI-005,Penjualan Cost Center,IDR,1,posted,,sales,Modul Penjualan,sales_invoice_posted,customer,CUST-002,Customer B,SLS-002,Sari Sales,1101,Kas Masuk,BRG-002,Barang Cost Center,5,PCS,CJR-ARTHA,CJR Artha,1500,0',
        'JRN-050125,2025-01-05,2025-01-05,SI-005,Penjualan Cost Center,IDR,1,posted,,sales,Modul Penjualan,sales_invoice_posted,customer,CUST-002,Customer B,SLS-002,Sari Sales,4101,Pendapatan,BRG-002,Barang Cost Center,5,PCS,CJR-ARTHA,CJR Artha,0,1500',
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-import-cost-center.csv', $csv);

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $journal = JournalEntry::query()->where('journal_no', 'JRN-050125')->firstOrFail();
    $line = $journal->lines()->orderBy('line_no')->firstOrFail();

    expect($line->dimension_details_json)->toBe([
        'cost_center' => [
            'code' => 'CJR-ARTHA',
            'name' => 'CJR Artha',
        ],
    ]);
});

it('downloads manual journal import template with cost center columns', function () {
    $ctx = createManualJournalImportContext();

    $response = $this
        ->actingAs($ctx['user'])
        ->get(route('apps.manual-journals.import-template'));

    $response->assertOk();

    $csv = $response->baseResponse->getContent();
    $firstLine = strtok(str_replace("\xEF\xBB\xBF", '', $csv), "\n");

    expect(str_getcsv($firstLine))->toContain('cost_center_code')
        ->and(str_getcsv($firstLine))->toContain('cost_center_name');
});
