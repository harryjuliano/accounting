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
