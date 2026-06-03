<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationClientCredential;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use App\Models\User;

function createModulePresetJournalContext(): array
{
    $company = Company::create([
        'code' => 'CMP-PRESET',
        'name' => 'PT Module Preset',
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
        'code' => 'BR-CASH',
        'name' => 'Cash Branch',
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

    $expenseAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '6101.001',
        'name' => 'Office Supplies Expense',
        'account_type' => 'expense',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'profit_loss',
        'is_active' => true,
    ]);

    $bankAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1101.001',
        'name' => 'Operating Bank',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    $headerAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1100.000',
        'name' => 'Cash Header',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
        'allow_manual_posting' => false,
    ]);

    $clientSecret = 'cash-secret-12345';
    $credential = IntegrationClientCredential::create([
        'client_key' => 'CASH-BANK-KEY',
        'client_secret_hash' => hash('sha256', $clientSecret),
        'source_module' => 'cash_bank',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'client_name' => 'Cash Bank Module',
        'is_active' => true,
    ]);

    return compact('company', 'branch', 'expenseAccount', 'bankAccount', 'headerAccount', 'credential', 'clientSecret');
}

it('receives generic module preset journal events and stores them in the integration inbox', function () {
    $ctx = createModulePresetJournalContext();

    $this->postJson(route('api.integrations.events.store'), [
        'client_key' => $ctx['credential']->client_key,
        'client_secret' => $ctx['clientSecret'],
        'source_module' => 'cash_bank',
        'event_name' => 'cash.payment.posted',
        'event_datetime' => '2026-05-31 10:00:00',
        'idempotency_key' => 'cash-payment:CP-0001',
        'source_document_type' => 'cash_payment',
        'source_document_id' => 'CP-0001',
        'source_document_no' => 'CP-0001',
        'payload' => [
            'posting_mode' => 'module_preset',
            'posting_date' => '2026-05-31',
            'currency_code' => 'IDR',
            'source_module_name' => 'Modul Kas Bank',
            'counterparty_type' => 'vendor',
            'counterparty_code' => 'VEND-001',
            'counterparty_name' => 'Vendor ATK',
            'salesperson_code' => 'SLS-001',
            'salesperson_name' => 'Budi Sales',
            'journal' => [
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_code' => '6101.001',
                        'amount' => 125000,
                        'item_code' => 'ATK-001',
                        'item_name' => 'Office Supplies Pack',
                        'quantity' => 5,
                        'quantity_uom' => 'PACK',
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_code' => '1101.001',
                        'amount' => 125000,
                    ],
                ],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.source_module', 'cash_bank')
        ->assertJsonPath('data.processing_status', 'received');

    $event = IntegrationEvent::query()->firstOrFail();

    expect($event->source_module)->toBe('cash_bank')
        ->and($event->event_name)->toBe('cash.payment.posted')
        ->and(data_get($event->payload_json, 'posting_mode'))->toBe('module_preset')
        ->and(data_get($event->payload_json, 'source_module_name'))->toBe('Modul Kas Bank')
        ->and(data_get($event->payload_json, 'counterparty_code'))->toBe('VEND-001')
        ->and(data_get($event->payload_json, 'journal.lines.0.item_code'))->toBe('ATK-001')
        ->and(data_get($event->payload_json, '_meta.branch_id'))->toBe($ctx['branch']->id);
});

it('validates and posts module preset journal payloads without posting rules', function () {
    $ctx = createModulePresetJournalContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'cash_bank',
        'event_name' => 'cash.payment.posted',
        'source_document_type' => 'cash_payment',
        'source_document_id' => 'CP-0002',
        'source_document_no' => 'CP-0002',
        'idempotency_key' => 'cash-payment:CP-0002',
        'event_datetime' => '2026-05-31 11:00:00',
        'processing_status' => 'received',
        'payload_json' => [
            'posting_mode' => 'module_preset',
            'entry_date' => '2026-05-31',
            'posting_date' => '2026-05-31',
            'reference_no' => 'CP-0002',
            'description' => 'Cash payment with module preset journal',
            'currency_code' => 'IDR',
            'source_module_name' => 'Modul Kas Bank',
            'counterparty_type' => 'vendor',
            'counterparty_code' => 'VEND-001',
            'counterparty_name' => 'Vendor ATK',
            'salesperson_code' => 'SLS-001',
            'salesperson_name' => 'Budi Sales',
            'exchange_rate' => 1,
            '_meta' => [
                'branch_id' => $ctx['branch']->id,
            ],
            'journal' => [
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_code' => '6101.001',
                        'amount' => 125000,
                        'description' => 'Office supplies',
                        'item_code' => 'ATK-001',
                        'item_name' => 'Office Supplies Pack',
                        'quantity' => 5,
                        'quantity_uom' => 'PACK',
                        'dimensions' => ['cost_center' => 'HO'],
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_id' => $ctx['bankAccount']->id,
                        'account_code' => '1101.001',
                        'amount' => 125000,
                        'description' => 'Paid from operating bank',
                        'dimensions' => ['cost_center' => 'HO'],
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('integration:module-preset:validate --module=cash_bank --limit=10')->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('validated')
        ->and(data_get($event->payload_json, '_posting_mode'))->toBe('module_preset')
        ->and(data_get($event->payload_json, '_posting_preview.total_debit'))->toBe(125000.0)
        ->and(data_get($event->payload_json, '_posting_preview.total_credit'))->toBe(125000.0)
        ->and(data_get($event->payload_json, '_posting_preview.lines.0.account_id'))->toBe($ctx['expenseAccount']->id)
        ->and(data_get($event->payload_json, '_posting_preview.lines.1.account_id'))->toBe($ctx['bankAccount']->id);

    $this->artisan('integration:module-preset:post --module=cash_bank --limit=10')->assertSuccessful();

    $event->refresh();

    $journal = JournalEntry::query()->where('integration_key', 'cash_bank:event:' . $event->id)->firstOrFail();
    $lines = $journal->lines()->orderBy('line_no')->get();

    expect($event->processing_status)->toBe('processed')
        ->and($journal->source_module)->toBe('cash_bank')
        ->and($journal->source_event)->toBe('cash.payment.posted')
        ->and($journal->journal_no)->toStartWith('CASH-BANK-AUTO-20260531-')
        ->and($journal->status)->toBe('posted')
        ->and($journal->branch_id)->toBe($ctx['branch']->id)
        ->and($journal->source_module_name)->toBe('Modul Kas Bank')
        ->and($journal->counterparty_type)->toBe('vendor')
        ->and($journal->counterparty_code)->toBe('VEND-001')
        ->and($journal->counterparty_name)->toBe('Vendor ATK')
        ->and($journal->salesperson_code)->toBe('SLS-001')
        ->and($journal->salesperson_name)->toBe('Budi Sales')
        ->and((float) $journal->total_debit)->toBe(125000.0)
        ->and((float) $journal->total_credit)->toBe(125000.0)
        ->and($lines)->toHaveCount(2)
        ->and((float) $lines[0]->debit)->toBe(125000.0)
        ->and((float) $lines[1]->credit)->toBe(125000.0)
        ->and($lines[0]->item_code)->toBe('ATK-001')
        ->and($lines[0]->item_name)->toBe('Office Supplies Pack')
        ->and((float) $lines[0]->quantity)->toBe(5.0)
        ->and($lines[0]->quantity_uom)->toBe('PACK')
        ->and($lines[0]->dimension_details_json)->toBe(['cost_center' => 'HO']);
});

it('rejects unbalanced module preset journal payloads during validation', function () {
    $ctx = createModulePresetJournalContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'cash_bank',
        'event_name' => 'cash.receipt.posted',
        'idempotency_key' => 'cash-receipt:CR-0001',
        'event_datetime' => '2026-05-31 12:00:00',
        'processing_status' => 'received',
        'payload_json' => [
            'posting_mode' => 'module_preset',
            'journal' => [
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_code' => '1101.001',
                        'amount' => 200000,
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_code' => '6101.001',
                        'amount' => 150000,
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('integration:module-preset:validate --module=cash_bank --limit=10')->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('failed')
        ->and($event->error_message)->toBe('unbalanced_journal');

    $this->assertDatabaseHas('integration_failures', [
        'integration_event_id' => $event->id,
        'failure_stage' => 'validation',
        'error_code' => 'unbalanced_journal',
    ]);
});


it('rejects module preset journal lines posted to non-postable COA header accounts', function () {
    $ctx = createModulePresetJournalContext();

    IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'cash_bank',
        'event_name' => 'cash.receipt.posted',
        'idempotency_key' => 'cash-receipt:CR-HEADER-001',
        'event_datetime' => '2026-05-31 13:00:00',
        'processing_status' => 'received',
        'payload_json' => [
            'posting_mode' => 'module_preset',
            'journal' => [
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_code' => '1100.000',
                        'amount' => 200000,
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_code' => '6101.001',
                        'amount' => 200000,
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('integration:module-preset:validate --module=cash_bank --limit=10')->assertSuccessful();

    $event = \App\Models\IntegrationEvent::query()->where('idempotency_key', 'cash-receipt:CR-HEADER-001')->firstOrFail();

    expect($event->processing_status)->toBe('failed')
        ->and($event->error_message)->toBe('invalid_account_reference');
});
