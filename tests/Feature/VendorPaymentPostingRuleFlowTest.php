<?php

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\VendorPaymentPostingRuleSeeder;

function createVendorPaymentPostingContext(): array
{
    $company = Company::create([
        'code' => 'CMP-AP-PAY',
        'name' => 'PT AP Payment',
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

    $accounts = [];

    foreach ([
        ['code' => '2110', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '2130', 'name' => 'WHT Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '7120-050', 'name' => 'Pos & Meterai', 'account_type' => 'expense', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '7130-020', 'name' => 'Freight', 'account_type' => 'expense', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '7160-050', 'name' => 'Bank Charges', 'account_type' => 'expense', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '1120-010', 'name' => 'Bank BCA', 'account_type' => 'asset', 'normal_balance' => 'debit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '1120-020', 'name' => 'Bank Mandiri', 'account_type' => 'assets', 'normal_balance' => 'debit', 'financial_statement_group' => 'balance_sheet'],
    ] as $account) {
        $accounts[$account['code']] = ChartOfAccount::create(array_merge($account, [
            'company_id' => $company->id,
            'level' => 4,
            'is_active' => true,
        ]));
    }

    $bankAccount = BankAccount::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'bank_name' => 'BCA',
        'account_name' => 'PT AP Payment Operasional',
        'account_number' => '1234567890',
        'currency_code' => 'IDR',
        'gl_account_id' => $accounts['1120-010']->id,
        'is_active' => true,
    ]);

    app(VendorPaymentPostingRuleSeeder::class)->run();

    return compact('company', 'user', 'branch', 'accounts', 'bankAccount');
}

function createVendorPaymentEvent(array $ctx, string $status = 'received', array $payloadOverrides = []): IntegrationEvent
{
    return IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'accounts_payable',
        'event_name' => 'vendor.payment.posted',
        'source_document_type' => 'vendor_payment',
        'source_document_id' => 'VP-1001',
        'source_document_no' => 'VP-0001',
        'idempotency_key' => 'VP-FLOW-1001',
        'event_datetime' => '2026-05-31 10:00:00',
        'processing_status' => $status,
        'payload_json' => array_replace_recursive([
            'transaction_type' => 'vendor.payment.bank_transfer',
            'entry_date' => '2026-05-31',
            'posting_date' => '2026-05-31',
            'reference_no' => 'VP-0001',
            'description' => 'Vendor Payment VP-0001',
            'currency_code' => 'IDR',
            'cash_account_id' => $ctx['bankAccount']->id,
            '_meta' => [
                'branch_id' => $ctx['branch']->id,
            ],
            'amounts' => [
                'invoice_payment_total' => 6904000,
                'withholding_tax_total' => 128000,
                'stamp_duty' => 138080,
                'bank_charge' => 10000,
                'freight' => 100000,
            ],
        ], $payloadOverrides),
    ]);
}

it('validates vendor payment with selected cash account and WHT at payment', function () {
    $ctx = createVendorPaymentPostingContext();
    $event = createVendorPaymentEvent($ctx);

    $this->artisan('integration:vendor-payment:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated')
        ->and(data_get($event->payload_json, '_posting_rule.rule_code'))->toBe('AP_VENDOR_PAYMENT_BANK_TRANSFER')
        ->and(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(7152080.0)
        ->and(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(7152080.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines'))->toHaveCount(6)
        ->and(data_get($event->payload_json, '_posting_preview.lines.0.amount'))->toBe(6904000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines.4.amount'))->toBe(128000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines.5.amount'))->toBe(7024080.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines.5.account_id'))->toBe($ctx['accounts']['1120-010']->id);
});

it('posts validated vendor payment preview into auto journal lines', function () {
    $ctx = createVendorPaymentPostingContext();
    $event = createVendorPaymentEvent($ctx);

    $this->artisan('integration:vendor-payment:validate --limit=10')
        ->assertSuccessful();
    $this->artisan('integration:vendor-payment:post --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('processed');

    $journal = JournalEntry::query()
        ->where('integration_key', 'accounts_payable:vendor_payment:event:' . $event->id)
        ->firstOrFail();

    expect($journal->journal_type)->toBe('auto')
        ->and($journal->source_module)->toBe('accounts_payable')
        ->and($journal->source_event)->toBe('vendor.payment.posted')
        ->and($journal->journal_no)->toStartWith('VP-AUTO-20260531-')
        ->and($journal->status)->toBe('posted')
        ->and($journal->branch_id)->toBe($ctx['branch']->id)
        ->and($journal->reference_no)->toBe('VP-0001')
        ->and((float) $journal->total_debit)->toBe(7152080.0)
        ->and((float) $journal->total_credit)->toBe(7152080.0)
        ->and($journal->lines()->count())->toBe(6);

    $lines = $journal->lines()->orderBy('line_no')->get();

    expect((float) $lines[0]->debit)->toBe(6904000.0)
        ->and((float) $lines[1]->debit)->toBe(138080.0)
        ->and((float) $lines[2]->debit)->toBe(10000.0)
        ->and((float) $lines[3]->debit)->toBe(100000.0)
        ->and((float) $lines[4]->credit)->toBe(128000.0)
        ->and((float) $lines[5]->credit)->toBe(7024080.0)
        ->and($lines[5]->account_id)->toBe($ctx['accounts']['1120-010']->id);
});


it('validates vendor payment using GL account code without Finance Hub bank account master', function () {
    $ctx = createVendorPaymentPostingContext();
    BankAccount::query()->delete();

    $event = createVendorPaymentEvent($ctx, payloadOverrides: [
        'cash_account_id' => null,
        'bank_account_id' => null,
        'gl_account_code' => '1120-020',
        'source_cash_account' => [
            'id' => 3,
            'code' => 'B-1002',
            'name' => 'Bank Mandiri',
            'cash_type' => 'BANK',
            'currency_code' => 'IDR',
        ],
        'amounts' => [
            'invoice_payment_total' => 1665000,
            'withholding_tax_total' => 0,
            'stamp_duty' => 0,
            'bank_charge' => 0,
            'freight' => 0,
        ],
        'invoice_lines' => [
            [
                'invoice_no' => 'INV-10001',
                'payment_amount' => 1665000,
                'withholding_tax' => 0,
            ],
        ],
    ]);

    $this->artisan('integration:vendor-payment:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated')
        ->and(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(1665000.0)
        ->and(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(1665000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines'))->toHaveCount(2)
        ->and(data_get($event->payload_json, '_posting_preview.lines.1.account_id'))->toBe($ctx['accounts']['1120-020']->id);
});

it('validates vendor payment without payment WHT when WHT was handled at invoice', function () {
    $ctx = createVendorPaymentPostingContext();
    $event = createVendorPaymentEvent($ctx, payloadOverrides: [
        'amounts' => [
            'invoice_payment_total' => 6776000,
            'withholding_tax_total' => 0,
            'stamp_duty' => 138080,
            'bank_charge' => 10000,
            'freight' => 100000,
        ],
    ]);

    $this->artisan('integration:vendor-payment:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated')
        ->and(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(7024080.0)
        ->and(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(7024080.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines'))->toHaveCount(5)
        ->and(data_get($event->payload_json, '_posting_preview.lines.*.amount'))->toBe([6776000.0, 138080.0, 10000.0, 100000.0, 7024080.0]);
});

it('reports missing selected cash account separately from COA mappings', function () {
    $ctx = createVendorPaymentPostingContext();
    $event = createVendorPaymentEvent($ctx, payloadOverrides: [
        'cash_account_id' => null,
        'bank_account_id' => null,
    ]);

    $this->artisan('integration:vendor-payment:validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('failed')
        ->and($event->error_message)->toBe('cash_bank_account_not_found');
});
