<?php

use App\Models\Company;
use App\Models\IntegrationEvent;
use Illuminate\Support\Facades\Config;

function createInventoryIntegrationCompany(): Company
{
    return Company::create([
        'code' => 'CMP-INV',
        'name' => 'PT Inventory Integrasi',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);
}

it('receives inventory event payload from api and stores integration inbox data', function () {
    $company = createInventoryIntegrationCompany();

    $payload = [
        'company_id' => $company->id,
        'event_name' => 'inventory.receipt.posted',
        'event_datetime' => '2026-03-28T10:00:00Z',
        'idempotency_key' => 'INV-RECEIPT-1001',
        'source_document_type' => 'goods_receipt',
        'source_document_id' => 'GR-1001',
        'source_document_no' => 'GRN-1001',
        'schema_version' => 'v1',
        'payload' => [
            'branch_code' => 'JKT',
            'warehouse_code' => 'WH-01',
            'lines' => [
                ['item_code' => 'SKU-1', 'qty' => 10, 'unit_cost' => 25000],
            ],
        ],
    ];

    $response = $this->postJson('/api/integrations/inventory/events', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.processing_status', 'received')
        ->assertJsonPath('data.is_duplicate', false);

    $this->assertDatabaseHas('integration_events', [
        'company_id' => $company->id,
        'source_module' => 'inventory',
        'event_name' => 'inventory.receipt.posted',
        'idempotency_key' => 'INV-RECEIPT-1001',
        'processing_status' => 'received',
        'source_document_type' => 'goods_receipt',
        'source_document_id' => 'GR-1001',
    ]);

    $event = IntegrationEvent::query()->where('idempotency_key', 'INV-RECEIPT-1001')->firstOrFail();
    expect($event->payload_json['_meta']['schema_version'])->toBe('v1');
});

it('returns duplicate response and does not create second row for same idempotency key', function () {
    $company = createInventoryIntegrationCompany();

    $payload = [
        'company_id' => $company->id,
        'event_name' => 'inventory.cogs.posted',
        'event_datetime' => '2026-03-28T10:05:00Z',
        'idempotency_key' => 'INV-COGS-2001',
        'payload' => [
            'order_no' => 'SO-123',
            'amount' => 155000,
        ],
    ];

    $this->postJson('/api/integrations/inventory/events', $payload)->assertCreated();

    $duplicateResponse = $this->postJson('/api/integrations/inventory/events', $payload);

    $duplicateResponse
        ->assertOk()
        ->assertJsonPath('data.is_duplicate', true);

    expect(IntegrationEvent::query()->where('idempotency_key', 'INV-COGS-2001')->count())->toBe(1);
});

it('validates integration token header when token is configured', function () {
    $company = createInventoryIntegrationCompany();

    Config::set('services.integration.inventory_token', 'secure-token');

    $payload = [
        'company_id' => $company->id,
        'event_name' => 'inventory.adjustment.posted',
        'event_datetime' => '2026-03-28T11:00:00Z',
        'idempotency_key' => 'INV-ADJ-3001',
        'payload' => [
            'reason' => 'stock_opname',
            'amount' => 15000,
        ],
    ];

    $this->postJson('/api/integrations/inventory/events', $payload)
        ->assertUnauthorized();

    $this->withHeaders(['X-Integration-Token' => 'secure-token'])
        ->postJson('/api/integrations/inventory/events', $payload)
        ->assertCreated();
});
