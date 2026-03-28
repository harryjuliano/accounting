<?php

use App\Models\Company;
use App\Models\IntegrationEvent;
use App\Models\User;

it('renders integration event monitor page and includes event data', function () {
    $company = Company::create([
        'code' => 'CMP-INT-EVT',
        'name' => 'PT Integrasi Event',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    IntegrationEvent::create([
        'company_id' => $company->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-EVT-002',
        'payload_json' => ['total' => 150000],
        'event_datetime' => now(),
        'processing_status' => 'received',
    ]);

    $response = $this->actingAs($user)
        ->get(route('apps.integration-events.index'), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response
        ->assertOk()
        ->assertSee('Integration Event Monitor')
        ->assertSee('inventory.receipt.posted');
});
