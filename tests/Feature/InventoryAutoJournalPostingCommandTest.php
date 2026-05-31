<?php

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationEvent;
use App\Models\IntegrationOutbox;
use App\Models\JournalEntry;
use App\Models\User;

function createPhaseThreeContext(string $periodStatus = 'open'): array
{
    $company = Company::create([
        'code' => 'CMP-PH3',
        'name' => 'PT Phase 3',
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
        'code' => 'BR-01',
        'name' => 'Main Branch',
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
        'period_no' => 3,
        'period_name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'status' => $periodStatus,
    ]);

    $inventoryAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '1301',
        'name' => 'Inventory Asset',
        'account_type' => 'asset',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    $grniAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '2105',
        'name' => 'GRNI',
        'account_type' => 'liability',
        'normal_balance' => 'credit',
        'financial_statement_group' => 'balance_sheet',
        'is_active' => true,
    ]);

    $expenseAccount = ChartOfAccount::create([
        'company_id' => $company->id,
        'code' => '5120',
        'name' => 'Cost of Goods Sold',
        'account_type' => 'expense',
        'normal_balance' => 'debit',
        'financial_statement_group' => 'income_statement',
        'is_active' => true,
    ]);

    return compact('company', 'user', 'branch', 'inventoryAccount', 'grniAccount', 'expenseAccount');
}

it('posts validated inventory event into auto journal entry and journal lines', function () {
    $ctx = createPhaseThreeContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'source_document_type' => 'goods_receipt',
        'source_document_id' => 'GR-3001',
        'source_document_no' => 'GRN-3001',
        'idempotency_key' => 'INV-PH3-3001',
        'event_datetime' => '2026-03-28 09:00:00',
        'processing_status' => 'validated',
        'payload_json' => [
            'entry_date' => '2026-03-28',
            'posting_date' => '2026-03-28',
            'reference_no' => 'GRN-REF-3001',
            'description' => 'Inventory receipt auto journal',
            'currency_code' => 'IDR',
            '_meta' => [
                'branch_id' => $ctx['branch']->id,
            ],
            '_posting_preview' => [
                'currency_code' => 'IDR',
                'total_debit' => 50000,
                'total_credit' => 50000,
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_id' => $ctx['inventoryAccount']->id,
                        'amount' => 50000,
                        'description_template' => 'Inventory receipt asset posting',
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_id' => $ctx['grniAccount']->id,
                        'amount' => 50000,
                        'description_template' => 'Inventory receipt GRNI posting',
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('integration:inventory:post --limit=10')->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('processed');

    $journal = JournalEntry::query()->where('integration_key', 'inventory:event:' . $event->id)->firstOrFail();

    expect($journal->journal_type)->toBe('auto');
    expect($journal->status)->toBe('posted');
    expect($journal->branch_id)->toBe($ctx['branch']->id);
    expect($journal->reference_no)->toBe('GRN-REF-3001');
    expect($journal->description)->toBe('Inventory receipt auto journal');
    expect((float) $journal->total_debit)->toBe(50000.0);
    expect((float) $journal->total_credit)->toBe(50000.0);
    expect($journal->lines()->count())->toBe(2);
    expect(IntegrationOutbox::query()->count())->toBe(0);
});

it('posts inventory out event and enqueues finance hub outbox payload', function () {
    $ctx = createPhaseThreeContext();

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.issue.posted',
        'source_document_type' => 'inventory_issue',
        'source_document_id' => 'ISS-4001',
        'source_document_no' => 'ISS-4001',
        'idempotency_key' => 'INV-OUT-4001',
        'event_datetime' => '2026-03-28 09:00:00',
        'processing_status' => 'validated',
        'payload_json' => [
            'transaction_type' => 'inventory.issue.sales',
            'posting_date' => '2026-03-28',
            'reference_no' => 'SO-4001',
            'description' => 'Inventory issue sales posting',
            'currency_code' => 'IDR',
            '_meta' => [
                'branch_id' => $ctx['branch']->id,
            ],
            '_posting_preview' => [
                'currency_code' => 'IDR',
                'total_debit' => 75000,
                'total_credit' => 75000,
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_id' => $ctx['expenseAccount']->id,
                        'amount' => 75000,
                        'description_template' => 'Inventory issue sales COGS',
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_id' => $ctx['inventoryAccount']->id,
                        'amount' => 75000,
                        'description_template' => 'Inventory issue sales inventory reduction',
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('integration:inventory:post --limit=10')->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('processed');

    $journal = JournalEntry::query()->where('integration_key', 'inventory:event:' . $event->id)->firstOrFail();
    $outbox = IntegrationOutbox::query()->firstOrFail();

    expect($outbox->company_id)->toBe($ctx['company']->id);
    expect($outbox->integration_event_id)->toBe($event->id);
    expect($outbox->journal_entry_id)->toBe($journal->id);
    expect($outbox->destination_system)->toBe('finance_hub');
    expect($outbox->event_name)->toBe('finance_hub.inventory_out.posted');
    expect($outbox->status)->toBe('pending');
    expect(data_get($outbox->payload_json, 'transaction_type'))->toBe('inventory.issue.sales');
    expect(data_get($outbox->payload_json, 'journal.journal_no'))->toBe($journal->journal_no);
    expect(data_get($outbox->payload_json, 'journal.lines.0.account_code'))->toBe('5120');
    expect(data_get($outbox->payload_json, 'journal.lines.1.account_code'))->toBe('1301');
});

it('fails posting when accounting period is not open', function () {
    $ctx = createPhaseThreeContext(periodStatus: 'soft_closed');

    $event = IntegrationEvent::create([
        'company_id' => $ctx['company']->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-PH3-FAIL-1',
        'event_datetime' => '2026-03-28 09:00:00',
        'processing_status' => 'validated',
        'payload_json' => [
            '_posting_preview' => [
                'currency_code' => 'IDR',
                'lines' => [
                    [
                        'line_no' => 1,
                        'line_side' => 'debit',
                        'account_id' => $ctx['inventoryAccount']->id,
                        'amount' => 50000,
                    ],
                    [
                        'line_no' => 2,
                        'line_side' => 'credit',
                        'account_id' => $ctx['grniAccount']->id,
                        'amount' => 50000,
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('integration:inventory:post --limit=10')->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('failed');
    expect($event->error_message)->toBe('period_not_open');
    expect(JournalEntry::query()->count())->toBe(0);

    $this->assertDatabaseHas('integration_failures', [
        'integration_event_id' => $event->id,
        'failure_stage' => 'posting',
        'error_code' => 'period_not_open',
    ]);
});
