<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\IntegrationClientCredential;
use App\Models\IntegrationEvent;

function createVendorInvoiceApiCompany(): array
{
    $company = Company::create([
        'code' => 'CMP-AP-API',
        'name' => 'PT AP Integrasi API',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'JKT',
        'name' => 'Jakarta',
    ]);

    $clientKey = 'AP-CLIENT-001';
    $clientSecret = 'vendor-invoice-secret';

    IntegrationClientCredential::create([
        'client_key' => $clientKey,
        'client_secret_hash' => hash('sha256', $clientSecret),
        'source_module' => 'accounts_payable',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    return compact('company', 'branch', 'clientKey', 'clientSecret');
}

it('receives vendor invoice event payload from api for postman integration', function () {
    $ctx = createVendorInvoiceApiCompany();

    $payload = [
        'client_key' => $ctx['clientKey'],
        'client_secret' => $ctx['clientSecret'],
        'event_name' => 'vendor.invoice.posted',
        'event_datetime' => '2026-05-30T10:00:00Z',
        'idempotency_key' => 'VI-POSTMAN-1001',
        'source_document_type' => 'vendor_invoice',
        'source_document_id' => 'VI-1001',
        'source_document_no' => 'VI-0001',
        'schema_version' => 'v1',
        'payload' => [
            'transaction_type' => 'vendor.invoice.standard',
            'currency_code' => 'IDR',
            'posting_date' => '2026-05-30',
            'reference_no' => 'VI-0001',
            'description' => 'Vendor Invoice VI-0001',
            'amounts' => [
                'invoice' => 6400000,
                'tax' => 704000,
                'freight' => 100000,
                'withholding_tax' => 128000,
                'purchase_discount' => 200000,
                'payable_total' => 6876000,
            ],
        ],
    ];

    $response = $this->postJson('/api/integrations/vendor-invoices/events', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.processing_status', 'received')
        ->assertJsonPath('data.is_duplicate', false)
        ->assertJsonPath('data.company_id', $ctx['company']->id)
        ->assertJsonPath('data.branch_id', $ctx['branch']->id);

    $this->assertDatabaseHas('integration_events', [
        'company_id' => $ctx['company']->id,
        'source_module' => 'accounts_payable',
        'event_name' => 'vendor.invoice.posted',
        'idempotency_key' => 'VI-POSTMAN-1001',
        'processing_status' => 'received',
        'source_document_type' => 'vendor_invoice',
        'source_document_id' => 'VI-1001',
    ]);

    $event = IntegrationEvent::query()->where('idempotency_key', 'VI-POSTMAN-1001')->firstOrFail();
    expect($event->payload_json['_meta']['ingested_via'])->toBe('vendor_invoice_api')
        ->and($event->payload_json['_meta']['company_id'])->toBe($ctx['company']->id)
        ->and($event->payload_json['_meta']['branch_id'])->toBe($ctx['branch']->id)
        ->and($event->payload_json['amounts']['payable_total'])->toBe(6876000)
        ->and($event->payload_json['amounts']['purchase_discount'])->toBe(200000);
});


it('accepts all-module client credentials on vendor invoice endpoint', function () {
    $ctx = createVendorInvoiceApiCompany();
    $clientSecret = 'shared-all-secret';

    IntegrationClientCredential::create([
        'client_key' => 'ALL-CLIENT-001',
        'client_secret_hash' => hash('sha256', $clientSecret),
        'source_module' => 'all',
        'company_id' => $ctx['company']->id,
        'branch_id' => $ctx['branch']->id,
        'is_active' => true,
    ]);

    $payload = [
        'client_key' => 'ALL-CLIENT-001',
        'client_secret' => $clientSecret,
        'event_name' => 'vendor.invoice.posted',
        'event_datetime' => '2026-05-30T10:00:00Z',
        'idempotency_key' => 'VI-POSTMAN-ALL-1001',
        'source_document_type' => 'vendor_invoice',
        'source_document_id' => 'VI-ALL-1001',
        'source_document_no' => 'VI-ALL-0001',
        'payload' => [
            'transaction_type' => 'vendor.invoice.standard',
            'amounts' => [
                'invoice' => 6400000,
                'tax' => 704000,
                'freight' => 100000,
                'withholding_tax' => 128000,
                'purchase_discount' => 200000,
                'payable_total' => 6876000,
            ],
        ],
    ];

    $this->postJson('/api/integrations/vendor-invoices/events', $payload)
        ->assertCreated()
        ->assertJsonPath('data.company_id', $ctx['company']->id)
        ->assertJsonPath('data.branch_id', $ctx['branch']->id);

    $this->assertDatabaseHas('integration_events', [
        'company_id' => $ctx['company']->id,
        'source_module' => 'accounts_payable',
        'idempotency_key' => 'VI-POSTMAN-ALL-1001',
        'processing_status' => 'received',
    ]);
});

it('rejects vendor invoice api payload when accounts payable client credentials are invalid', function () {
    $ctx = createVendorInvoiceApiCompany();

    $payload = [
        'client_key' => $ctx['clientKey'],
        'client_secret' => 'wrong-secret',
        'event_name' => 'vendor.invoice.posted',
        'event_datetime' => '2026-05-30T10:00:00Z',
        'idempotency_key' => 'VI-POSTMAN-1002',
        'payload' => [
            'transaction_type' => 'vendor.invoice.standard',
            'amounts' => [
                'invoice' => 6400000,
                'tax' => 704000,
                'freight' => 100000,
                'withholding_tax' => 128000,
                'purchase_discount' => 200000,
                'payable_total' => 6876000,
            ],
        ],
    ];

    $this->postJson('/api/integrations/vendor-invoices/events', $payload)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid client credential for vendor invoice events. Use a client_key/client_secret generated with --module=accounts_payable or --module=all.');
});

it('rejects inventory client credentials on vendor invoice endpoint with actionable message', function () {
    $ctx = createVendorInvoiceApiCompany();
    $inventoryClientSecret = 'inventory-secret';

    IntegrationClientCredential::create([
        'client_key' => 'INVENTORY-7XWOBMBKVX9S',
        'client_secret_hash' => hash('sha256', $inventoryClientSecret),
        'source_module' => 'inventory',
        'company_id' => $ctx['company']->id,
        'branch_id' => $ctx['branch']->id,
        'is_active' => true,
    ]);

    $payload = [
        'client_key' => 'INVENTORY-7XWOBMBKVX9S',
        'client_secret' => $inventoryClientSecret,
        'event_name' => 'vendor.invoice.posted',
        'event_datetime' => '2026-05-30T10:00:00Z',
        'idempotency_key' => 'VI-POSTMAN-1003',
        'payload' => [
            'transaction_type' => 'vendor.invoice.standard',
            'amounts' => [
                'invoice' => 6400000,
                'tax' => 704000,
                'freight' => 100000,
                'withholding_tax' => 128000,
                'purchase_discount' => 200000,
                'payable_total' => 6876000,
            ],
        ],
    ];

    $this->postJson('/api/integrations/vendor-invoices/events', $payload)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid client credential for vendor invoice events. Use a client_key/client_secret generated with --module=accounts_payable or --module=all.');
});
