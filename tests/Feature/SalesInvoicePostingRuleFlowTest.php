<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\CoaMapping;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use App\Models\PostingRule;
use App\Models\User;
use Database\Seeders\SalesInvoicePostingRuleSeeder;

function createSalesInvoicePostingContext(): array
{
    $company = Company::create([
        'code' => 'CMP-SALES',
        'name' => 'PT Sales Invoice',
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

    User::factory()->create([
        'company_id' => $company->id,
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'BR-SALES',
        'name' => 'Sales Branch',
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
        'period_no' => 6,
        'period_name' => 'June 2026',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => 'open',
    ]);

    $accounts = collect([
        ['code' => '1130', 'name' => 'Accounts Receivable', 'account_type' => 'asset', 'normal_balance' => 'debit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '4120', 'name' => 'Trading Revenue', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'financial_statement_group' => 'income_statement'],
        ['code' => '4130', 'name' => 'Sales Discount', 'account_type' => 'revenue', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '4140', 'name' => 'Freight Income', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'financial_statement_group' => 'income_statement'],
        ['code' => '2130', 'name' => 'Taxes Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'financial_statement_group' => 'balance_sheet'],
        ['code' => '5120', 'name' => 'Cost of Goods Sold', 'account_type' => 'expense', 'normal_balance' => 'debit', 'financial_statement_group' => 'income_statement'],
        ['code' => '1150', 'name' => 'Inventory', 'account_type' => 'asset', 'normal_balance' => 'debit', 'financial_statement_group' => 'balance_sheet'],
    ])->mapWithKeys(fn (array $account) => [
        $account['code'] => ChartOfAccount::create(array_merge($account, [
            'company_id' => $company->id,
            'is_active' => true,
        ])),
    ]);

    app(SalesInvoicePostingRuleSeeder::class)->run();

    CoaMapping::query()
        ->where('company_id', $company->id)
        ->where('module_name', 'sales')
        ->where('mapping_key', 'sales.invoice.debit.discount')
        ->update(['account_id' => $accounts['4130']->id]);

    CoaMapping::query()
        ->where('company_id', $company->id)
        ->where('module_name', 'sales')
        ->where('mapping_key', 'sales.invoice.credit.freight_income')
        ->update(['account_id' => $accounts['4140']->id]);

    return compact('company', 'branch', 'accounts');
}

it('validates and posts sales invoice combined journal with VAT after discount and dispatch COGS', function () {
    $ctx = createSalesInvoicePostingContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'sales',
        'event_name' => 'sales.invoice.posted',
        'source_document_type' => 'sales_invoice',
        'source_document_id' => 'INV-202606-000001',
        'source_document_no' => 'INV-202606-000001',
        'idempotency_key' => 'sales-invoice:INV-202606-000001:posted:v1',
        'event_datetime' => '2026-06-01 10:00:00',
        'processing_status' => 'received',
        'payload_json' => [
            'transaction_type' => 'sales.invoice.standard',
            'posting_date' => '2026-06-01',
            'reference_no' => 'INV-202606-000001',
            'description' => 'Sales invoice INV-202606-000001',
            'currency_code' => 'IDR',
            'tax' => [
                'rate' => 0.11,
            ],
            'amounts' => [
                'subtotal' => 1437500,
                'discount' => 100000,
                'shipping_fee' => 200000,
                'cogs' => 1437500,
            ],
            '_meta' => [
                'branch_id' => $ctx['branch']->id,
            ],
        ],
    ]);

    $this->artisan('integration:sales-invoice:validate --limit=10')->assertSuccessful();

    $event->refresh();
    expect($event->processing_status)->toBe('validated');
    expect(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(3222125.0);
    expect(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(3222125.0);
    expect(data_get($event->payload_json, '_posting_preview.lines.0.amount'))->toBe(1684625.0);
    expect(data_get($event->payload_json, '_posting_preview.lines.3.amount'))->toBe(147125.0);

    $this->artisan('integration:sales-invoice:post --limit=10')->assertSuccessful();

    $event->refresh();
    expect($event->processing_status)->toBe('processed');

    $journal = JournalEntry::query()->where('integration_key', 'sales:event:' . $event->id)->firstOrFail();
    expect($journal->source_module)->toBe('sales');
    expect($journal->source_event)->toBe('sales.invoice.posted');
    expect($journal->reference_no)->toBe('INV-202606-000001');
    expect((float) $journal->total_debit)->toBe(3222125.0);
    expect((float) $journal->total_credit)->toBe(3222125.0);
    expect($journal->lines()->count())->toBe(7);
});

it('stores sales invoice preset setup from the preset jurnal menu route', function () {
    $ctx = createSalesInvoicePostingContext();
    $user = User::query()->where('company_id', $ctx['company']->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('apps.preset-journals.store'), [
            'company_id' => $ctx['company']->id,
            'module_name' => 'sales',
            'event_name' => 'sales.invoice.posted',
            'transaction_type' => 'sales.invoice.standard',
            'rule_code' => 'SALES_INVOICE_POSTED_CUSTOM',
            'rule_name' => 'Custom Sales Invoice Posted Combined Journal',
            'version' => 1,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'priority' => 90,
            'is_active' => true,
            'description' => 'Custom preset from menu.',
            'lines' => [
                [
                    'line_no' => 1,
                    'line_side' => 'debit',
                    'account_source_type' => 'mapping',
                    'mapping_key' => 'sales.invoice.debit.ar.custom',
                    'amount_source' => 'formula',
                    'formula_json_text' => '{"type":"sales_invoice_receivable_total"}',
                ],
                [
                    'line_no' => 2,
                    'line_side' => 'credit',
                    'account_source_type' => 'mapping',
                    'mapping_key' => 'sales.invoice.credit.revenue.custom',
                    'amount_source' => 'formula',
                    'formula_json_text' => '{"type":"path","path":"amounts.subtotal"}',
                ],
            ],
            'coa_mappings' => [
                [
                    'mapping_key' => 'sales.invoice.debit.ar.custom',
                    'account_id' => $ctx['accounts']['1130']->id,
                    'description' => 'Custom AR',
                ],
                [
                    'mapping_key' => 'sales.invoice.credit.revenue.custom',
                    'account_id' => $ctx['accounts']['4120']->id,
                    'description' => 'Custom revenue',
                ],
            ],
        ])
        ->assertRedirect(route('apps.preset-journals.index'));

    $rule = PostingRule::query()->where('rule_code', 'SALES_INVOICE_POSTED_CUSTOM')->firstOrFail();
    expect($rule->module_name)->toBe('sales');
    expect($rule->lines()->count())->toBe(2);
    expect($rule->lines()->first()->formula_json)->toBe(['type' => 'sales_invoice_receivable_total']);
});
