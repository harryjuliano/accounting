<?php

use App\Models\Company;
use App\Models\IntegrationEvent;
use App\Models\IntegrationFailure;

it('requeues failed validation events back to received', function () {
    $company = Company::create([
        'code' => 'CMP-RETRY',
        'name' => 'PT Retry',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $event = IntegrationEvent::create([
        'company_id' => $company->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-RETRY-01',
        'payload_json' => ['amounts' => ['total' => 1000]],
        'event_datetime' => '2026-03-28 09:00:00',
        'processing_status' => 'failed',
        'error_message' => 'posting_rule_not_found',
    ]);

    IntegrationFailure::create([
        'integration_event_id' => $event->id,
        'failure_stage' => 'validation',
        'error_code' => 'posting_rule_not_found',
        'error_message' => 'Inventory validation failed: posting_rule_not_found',
        'retry_count' => 0,
        'last_retry_at' => now(),
    ]);

    $this->artisan('integration:inventory:retry-failed --stage=validate --limit=10')
        ->assertSuccessful();

    $event->refresh();

    expect($event->processing_status)->toBe('received');
    expect($event->error_message)->toBeNull();

    $this->assertDatabaseHas('integration_event_logs', [
        'integration_event_id' => $event->id,
        'message' => 'Failed validation event requeued to received.',
    ]);
});
