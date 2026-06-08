<?php

use App\Http\Controllers\Apps\ManualJournalController;
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

    ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Aset Header',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
        'allow_manual_posting' => false,
    ]);

    return compact('user', 'company');
}
it('downloads manual journal import template as xlsx attachment', function () {
    $company = Company::create([
        'code' => 'CMP-TPL-MJ',
        'name' => 'PT Template Manual Journal',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $response = $this->actingAs($user)->get(route('apps.manual-journals.import-template'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('Content-Disposition', 'attachment; filename="manual-journal-import-template.xlsx"');
    $response->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

    $tempFile = tempnam(sys_get_temp_dir(), 'manual-journal-template-test-');
    file_put_contents($tempFile, $response->getContent());

    $zip = new ZipArchive();
    expect($zip->open($tempFile))->toBeTrue();
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($tempFile);

    expect($sheetXml)->toContain('journal_no')
        ->and($sheetXml)->toContain('entry_date')
        ->and($sheetXml)->toContain('credit');
});


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

it('imports manual journals from xlsx template', function () {
    $ctx = createManualJournalImportContext();
    $rows = [
        ['journal_no', 'entry_date', 'posting_date', 'reference_no', 'description', 'currency_code', 'exchange_rate', 'status', 'branch_code', 'source_module', 'source_module_name', 'source_event', 'counterparty_type', 'counterparty_code', 'counterparty_name', 'salesperson_code', 'salesperson_name', 'account_code', 'line_description', 'item_code', 'item_name', 'quantity', 'quantity_uom', 'cost_center_code', 'cost_center_name', 'debit', 'credit'],
        ['JRN-XLSX-010125', '2025-01-01', '2025-01-01', 'XLSX-001', 'Import dari template Excel', 'IDR', '1', 'posted', '', '', '', '', '', '', '', '', '', '1101', 'Kas', '', '', '', '', '', '', '1000', '0'],
        ['JRN-XLSX-010125', '2025-01-01', '2025-01-01', 'XLSX-001', 'Import dari template Excel', 'IDR', '1', 'posted', '', '', '', '', '', '', '', '', '', '4101', 'Pendapatan', '', '', '', '', '', '', '0', '1000'],
    ];
    $method = new ReflectionMethod(ManualJournalController::class, 'buildSimpleXlsx');
    $method->setAccessible(true);
    $xlsx = $method->invoke(new ManualJournalController(), $rows, 'manual-journal-import-template');
    $file = UploadedFile::fake()->createWithContent('manual-journal-import-template.xlsx', $xlsx);

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
        'journal_no' => 'JRN-XLSX-010125',
        'entry_date' => '2025-01-01',
        'posting_date' => '2025-01-01',
        'status' => 'posted',
    ]);
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

it('imports tab-delimited manual journals with comma decimal amounts from excel', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        "journal_no\tentry_date\tposting_date\treference_no\tdescription\tcurrency_code\texchange_rate\tstatus\tbranch_code\taccount_code\tline_description\tdebit\tcredit",
        "JRN-060125\t2025-01-06\t2025-01-06\tSIS-006\tSaldo Awal Decimal Koma\tIDR\t1\tposted\t\t1101\tDebit decimal koma\t64419,0054\t0",
        "JRN-060125\t2025-01-06\t2025-01-06\tSIS-006\tSaldo Awal Decimal Koma\tIDR\t1\tposted\t\t4101\tCredit decimal koma\t0\t64419,0054",
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-import-decimal-comma.txt', $csv);

    $response = $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $journal = JournalEntry::query()->where('journal_no', 'JRN-060125')->firstOrFail();
    expect((float) $journal->lines()->sum('debit'))->toBe(64419.0054);
    expect((float) $journal->lines()->sum('credit'))->toBe(64419.0054);
    expect((float) $journal->lines()->sum('debit'))->not->toBe(644190054.0);
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


it('rejects manual journal imports posted to non-postable COA header accounts', function () {
    $ctx = createManualJournalImportContext();

    $csv = implode("\n", [
        'journal_no,entry_date,posting_date,reference_no,description,currency_code,exchange_rate,status,branch_code,account_code,line_description,debit,credit',
        'JRN-050125,2025-01-05,2025-01-05,SIS-005,Header COA Test,IDR,1,posted,,1000,Aset Header,1000,0',
        'JRN-050125,2025-01-05,2025-01-05,SIS-005,Header COA Test,IDR,1,posted,,4101,Pendapatan,0,1000',
    ]);

    $file = UploadedFile::fake()->createWithContent('manual-journal-header-coa.csv', $csv);

    $this
        ->actingAs($ctx['user'])
        ->post(route('apps.manual-journals.import'), [
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');

    expect(JournalEntry::query()->where('journal_no', 'JRN-050125')->exists())->toBeFalse();
});
