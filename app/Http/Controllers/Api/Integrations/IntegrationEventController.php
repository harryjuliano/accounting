<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integrations\IntegrationEventRequest;
use App\Models\IntegrationEvent;
use App\Services\Integrations\IntegrationClientCredentialService;
use App\Services\Integrations\IntegrationEventLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class IntegrationEventController extends Controller
{
    public function __construct(
        private readonly IntegrationEventLifecycleService $lifecycle,
        private readonly IntegrationClientCredentialService $credentialService,
    ) {
    }

    public function store(IntegrationEventRequest $request): JsonResponse
    {
        $this->ensureValidIntegrationToken($request->header('X-Integration-Token'));

        $validated = $request->validated();
        $payload = $request->input('payload', []);
        $sourceModule = Str::lower((string) $validated['source_module']);

        $credential = $this->credentialService->resolve(
            $sourceModule,
            (string) $validated['client_key'],
            (string) $validated['client_secret'],
        );

        abort_unless(
            $credential,
            401,
            'Invalid client credential for integration events. Use a client_key/client_secret generated with --module=' . $sourceModule . ' or --module=all.'
        );

        $event = IntegrationEvent::query()->firstOrCreate(
            [
                'company_id' => $credential->company_id,
                'idempotency_key' => $validated['idempotency_key'],
            ],
            [
                'source_module' => $sourceModule,
                'event_name' => $validated['event_name'],
                'source_document_type' => $validated['source_document_type'] ?? null,
                'source_document_id' => $validated['source_document_id'] ?? null,
                'source_document_no' => $validated['source_document_no'] ?? null,
                'payload_json' => array_merge(
                    is_array($payload) ? $payload : [],
                    [
                        '_meta' => [
                            'schema_version' => $validated['schema_version'] ?? 'v1',
                            'ingested_via' => 'generic_integration_api',
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

        $this->lifecycle->log($event, $isDuplicate ? 'warning' : 'info', $isDuplicate ? 'Duplicate integration event received.' : 'Integration event received.', [
            'idempotency_key' => $validated['idempotency_key'],
            'source_module' => $sourceModule,
            'event_name' => $validated['event_name'],
            'client_key' => $credential->client_key,
            'company_id' => $credential->company_id,
            'branch_id' => $credential->branch_id,
        ]);

        return response()->json([
            'message' => $isDuplicate ? 'Duplicate event received. Existing event reused.' : 'Integration event received.',
            'data' => [
                'integration_event_id' => $event->id,
                'source_module' => $event->source_module,
                'processing_status' => $event->processing_status,
                'is_duplicate' => $isDuplicate,
                'company_id' => $credential->company_id,
                'branch_id' => $credential->branch_id,
            ],
        ], $isDuplicate ? 200 : 201);
    }

    private function ensureValidIntegrationToken(?string $incomingToken): void
    {
        $expectedToken = config('services.integration.generic_token');

        if (! filled($expectedToken)) {
            return;
        }

        abort_unless(hash_equals((string) $expectedToken, (string) $incomingToken), 401, 'Invalid integration token.');
    }
}
