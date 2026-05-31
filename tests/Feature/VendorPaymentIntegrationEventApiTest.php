<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\IntegrationClientCredential;
use App\Models\IntegrationEvent;

function createVendorPaymentApiCompany(): array
{
    $company = Company::create([
        'code' => 'CMP-AP-PAY-API',
        'name' => 'PT AP Payment API',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $branch = Branch::create([
        'company_id' => $company->id,
        'code' => 'JKT',
        'name' => 'Jakarta',
    ]);

    $clientKey = 'AP-PAY-CLIENT-001';
    $clientSecret = 'vendor-payment-secret';

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

it('receives vendor payment event payload with GL account code instead of cash account id', function () {
    $ctx = createVendorPaymentApiCompany();

    $payload = [
        'client_key' => $ctx['clientKey'],
        'client_secret' => $ctx['clientSecret'],
        'event_name' => 'vendor.payment.posted',
        'event_datetime' => '2026-05-31T10:00:00Z',
        'idempotency_key' => 'VP-GL-CODE-1001',
        'source_document_type' => 'vendor_payment',
        'source_document_id' => 'VP-1001',
        'source_document_no' => 'VP-0001',
        'schema_version' => 'v1',
        'payload' => [
            'transaction_type' => 'vendor.payment.bank_transfer',
            'currency_code' => 'IDR',
            'posting_date' => '2026-05-31',
            'entry_date' => '2026-05-31',
            'reference_no' => 'VP-0001',
            'description' => 'Vendor Payment VP-0001',
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
        ],
    ];

    $response = $this->postJson('/api/integrations/vendor-payments/events', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.processing_status', 'received')
        ->assertJsonPath('data.is_duplicate', false)
        ->assertJsonPath('data.company_id', $ctx['company']->id)
        ->assertJsonPath('data.branch_id', $ctx['branch']->id);

    $event = IntegrationEvent::query()->where('idempotency_key', 'VP-GL-CODE-1001')->firstOrFail();

    expect(data_get($event->payload_json, 'gl_account_code'))->toBe('1120-020')
        ->and(data_get($event->payload_json, 'source_cash_account.code'))->toBe('B-1002')
        ->and(data_get($event->payload_json, 'cash_account_id'))->toBeNull();
});
