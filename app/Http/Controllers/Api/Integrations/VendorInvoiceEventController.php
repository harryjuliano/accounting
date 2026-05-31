<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integrations\VendorInvoiceEventRequest;
use App\Models\IntegrationEvent;
use App\Services\Integrations\IntegrationClientCredentialService;
use App\Services\Integrations\IntegrationEventLifecycleService;
use Illuminate\Http\JsonResponse;

class VendorInvoiceEventController extends Controller
{
    public function __construct(
        private readonly IntegrationEventLifecycleService $lifecycle,
        private readonly IntegrationClientCredentialService $credentialService,
    ) {
    }

    public function store(VendorInvoiceEventRequest $request): JsonResponse
    {
        $this->ensureValidIntegrationToken($request->header('X-Integration-Token'));

        $validated = $request->validated();

        $credential = $this->credentialService->resolve(
            'accounts_payable',
            (string) $validated['client_key'],
            (string) $validated['client_secret'],
        );

        abort_unless(
            $credential,
            401,
            'Invalid accounts_payable client credential. Use a client_key/client_secret generated with --module=accounts_payable for vendor invoice events.'
        );

        $event = IntegrationEvent::query()->firstOrCreate(
            [
                'company_id' => $credential->company_id,
                'idempotency_key' => $validated['idempotency_key'],
            ],
            [
                'source_module' => 'accounts_payable',
                'event_name' => $validated['event_name'],
                'source_document_type' => $validated['source_document_type'] ?? 'vendor_invoice',
                'source_document_id' => $validated['source_document_id'] ?? null,
                'source_document_no' => $validated['source_document_no'] ?? null,
                'payload_json' => array_merge(
                    $validated['payload'],
                    [
                        '_meta' => [
                            'schema_version' => $validated['schema_version'] ?? 'v1',
                            'ingested_via' => 'vendor_invoice_api',
                            'client_key' => $credential->client_key,
                            'company_id' => $credential->company_id,
                            'branch_id' => $credential->branch_id,
                        ],
                    ]
                ),
                'event_datetime' => $validated['event_datetime'],
                'processing_status' => 'received',
            ]
        );

        $isDuplicate = ! $event->wasRecentlyCreated;

        $this->lifecycle->log($event, $isDuplicate ? 'warning' : 'info', $isDuplicate ? 'Duplicate vendor invoice event received.' : 'Vendor invoice event received.', [
            'idempotency_key' => $validated['idempotency_key'],
            'event_name' => $validated['event_name'],
            'client_key' => $credential->client_key,
            'company_id' => $credential->company_id,
            'branch_id' => $credential->branch_id,
        ]);

        return response()->json([
            'message' => $isDuplicate ? 'Duplicate event received. Existing event reused.' : 'Vendor invoice event received.',
            'data' => [
                'integration_event_id' => $event->id,
                'processing_status' => $event->processing_status,
                'is_duplicate' => $isDuplicate,
                'company_id' => $credential->company_id,
                'branch_id' => $credential->branch_id,
            ],
        ], $isDuplicate ? 200 : 201);
    }

    private function ensureValidIntegrationToken(?string $incomingToken): void
    {
        $expectedToken = config('services.integration.vendor_invoice_token');

        if (! filled($expectedToken)) {
            return;
        }

        abort_unless(hash_equals((string) $expectedToken, (string) $incomingToken), 401, 'Invalid integration token.');
    }
}
