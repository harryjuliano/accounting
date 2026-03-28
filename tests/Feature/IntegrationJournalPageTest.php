<?php

use App\Models\Company;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use App\Models\User;

it('renders integration journal monitor page and includes integration data', function () {
    $company = Company::create([
        'code' => 'CMP-INT-JRN',
        'name' => 'PT Integrasi Monitor',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);


    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'year_label' => (string) now()->year,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
        'status' => 'open',
    ]);

    $period = AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => (int) now()->month,
        'period_name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'status' => 'open',
    ]);

    IntegrationEvent::create([
        'company_id' => $company->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-EVT-001',
        'payload_json' => ['total' => 100000],
        'event_datetime' => now(),
        'processing_status' => 'processed',
        'processed_at' => now(),
    ]);

    JournalEntry::create([
        'company_id' => $company->id,
        'accounting_period_id' => $period->id,
        'journal_no' => 'AUTO-INV-001',
        'journal_type' => 'auto',
        'source_module' => 'inventory',
        'source_event' => 'inventory.receipt.posted',
        'integration_key' => 'INV-EVT-001',
        'entry_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'description' => 'Auto journal',
        'currency_code' => 'IDR',
        'exchange_rate' => 1,
        'total_debit' => 100000,
        'total_credit' => 100000,
        'status' => 'posted',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('apps.manual-journals.integration-journal'), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response
        ->assertOk()
        ->assertSee('Integration Journal Monitor')
        ->assertSee('AUTO-INV-001')
        ->assertSee('inventory.receipt.posted');
});
