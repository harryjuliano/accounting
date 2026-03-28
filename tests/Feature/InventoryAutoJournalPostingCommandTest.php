<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\IntegrationEvent;
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

    return compact('company', 'user', 'inventoryAccount', 'grniAccount');
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
            'currency_code' => 'IDR',
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
    expect((float) $journal->total_debit)->toBe(50000.0);
    expect((float) $journal->total_credit)->toBe(50000.0);
    expect($journal->lines()->count())->toBe(2);
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
