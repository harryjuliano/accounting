<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\VendorInvoicePostingRuleSeeder;

function createVendorInvoicePostingContext(): array
{
    $company = Company::create([
        'code' => 'CMP-AP-FLOW',
        'name' => 'PT AP Flow',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    Currency::create([
        'code' => 'IDR',
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'decimal_places' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'JKT',
        'name' => 'Jakarta',
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
        'period_no' => 5,
        'period_name' => 'May 2026',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'status' => 'open',
    ]);

    foreach ([
        ['code' => '5120', 'name' => 'Vendor Invoice Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '1170', 'name' => 'Input VAT', 'account_type' => 'asset', 'normal_balance' => 'debit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '7130', 'name' => 'Freight Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '2130', 'name' => 'WHT Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '2110', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'financial_statement_group' => 'balance_sheet'],
    ] as $account) {
        ChartOfAccount::create(array_merge($account, [
            'company_id' => $company->id,
            'is_active' => true,
        ]));
    }

    app(VendorInvoicePostingRuleSeeder::class)->run();

    return compact('company', 'user', 'branch');
}

function createVendorInvoiceEvent(array $ctx, string $status = 'received', array $payloadOverrides = []): IntegrationEvent
{
    return IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'accounts_payable',
        'event_name' => 'vendor.invoice.posted',
        'source_document_type' => 'vendor_invoice',
        'source_document_id' => 'VI-1001',
        'source_document_no' => 'VI-0001',
        'idempotency_key' => 'VI-FLOW-1001',
        'event_datetime' => '2026-05-30 10:00:00',
        'processing_status' => $status,
        'payload_json' => array_replace_recursive([
            'transaction_type' => 'vendor.invoice.standard',
            'entry_date' => '2026-05-30',
            'posting_date' => '2026-05-30',
            'reference_no' => 'VI-0001',
            'description' => 'Vendor Invoice VI-0001',
            'currency_code' => 'IDR',
            '_meta' => [
                'branch_id' => $ctx['branch']->id,
            ],
            'amounts' => [
                'invoice' => 6400000,
                'tax' => 704000,
                'freight' => 100000,
                'withholding_tax' => 128000,
                'payable_total' => 7076000,
            ],
        ], $payloadOverrides),
    ]);
}

it('validates vendor invoice event into five-line posting preview', function () {
    $ctx = createVendorInvoicePostingContext();
    $event = createVendorInvoiceEvent($ctx);

    $this->artisan('integration:vendor-invoice:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated')
        ->and(data_get($event->payload_json, '_posting_rule.rule_code'))->toBe('AP_VENDOR_INVOICE_STANDARD')
        ->and(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(7204000.0)
        ->and(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(7204000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines'))->toHaveCount(5)
        ->and(data_get($event->payload_json, '_posting_preview.lines.0.amount'))->toBe(6400000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines.3.amount'))->toBe(128000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines.4.amount'))->toBe(7076000.0);
});

it('posts validated vendor invoice preview into auto journal lines', function () {
    $ctx = createVendorInvoicePostingContext();
    $event = createVendorInvoiceEvent($ctx);

    $this->artisan('integration:vendor-invoice:validate --limit=10')
        ->assertSuccessful();
    $this->artisan('integration:vendor-invoice:post --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('processed');

    $journal = JournalEntry::query()
        ->where('integration_key', 'accounts_payable:vendor_invoice:event:' . $event->id)
        ->firstOrFail();

    expect($journal->journal_type)->toBe('auto')
        ->and($journal->source_module)->toBe('accounts_payable')
        ->and($journal->source_event)->toBe('vendor.invoice.posted')
        ->and($journal->journal_no)->toStartWith('VI-AUTO-20260530-')
        ->and($journal->status)->toBe('posted')
        ->and($journal->branch_id)->toBe($ctx['branch']->id)
        ->and($journal->reference_no)->toBe('VI-0001')
        ->and((float) $journal->total_debit)->toBe(7204000.0)
        ->and((float) $journal->total_credit)->toBe(7204000.0)
        ->and($journal->lines()->count())->toBe(5);

    $lines = $journal->lines()->orderBy('line_no')->get();

    expect((float) $lines[0]->debit)->toBe(6400000.0)
        ->and((float) $lines[1]->debit)->toBe(704000.0)
        ->and((float) $lines[2]->debit)->toBe(100000.0)
        ->and((float) $lines[3]->credit)->toBe(128000.0)
        ->and((float) $lines[4]->credit)->toBe(7076000.0);
});

it('fails vendor invoice validation when payload amounts do not balance', function () {
    $ctx = createVendorInvoicePostingContext();
    $event = createVendorInvoiceEvent($ctx, payloadOverrides: [
        'amounts' => [
            'payable_total' => 7000000,
        ],
    ]);

    $this->artisan('integration:vendor-invoice:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('failed')
        ->and($event->error_message)->toBe('unbalanced_preview');
});
