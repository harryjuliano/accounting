<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Services\Integrations\InventoryAutoJournalService;
use App\Services\Integrations\InventoryPostingRuleEngine;
use App\Services\Integrations\ModulePresetAutoJournalService;
use App\Services\Integrations\ModulePresetJournalValidator;
use App\Services\Integrations\PostingMode;
use App\Services\Integrations\VendorInvoiceAutoJournalService;
use App\Services\Integrations\VendorInvoicePostingRuleEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class IntegrationEventController extends Controller implements HasMiddleware
{
    use InteractsWithCompanyScope;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboard-access|journal-entry-access|journal-entry-data'),
        ];
    }

    public function __invoke(Request $request)
    {
        $companyId = $request->user()?->company_id;

        $eventScope = fn (Builder $query) => $query
            ->when(
                $this->isCompanyAdmin() && $companyId,
                fn (Builder $builder) => $builder->where('company_id', $companyId)
            );

        $receivedEvents = IntegrationEvent::query()
            ->tap($eventScope)
            ->withCount([
                'failures as open_failure_count' => fn (Builder $query) => $query->whereNull('resolved_at'),
            ])
            ->select([
                'id',
                'company_id',
                'source_module',
                'event_name',
                'source_document_type',
                'source_document_no',
                'idempotency_key',
                'event_datetime',
                'payload_json',
                'processing_status',
                'processed_at',
                'error_message',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (IntegrationEvent $event) => [
                'id' => $event->id,
                'source_module' => $event->source_module,
                'event_name' => $event->event_name,
                'source_document_type' => $event->source_document_type,
                'source_document_no' => $event->source_document_no,
                'idempotency_key' => $event->idempotency_key,
                'event_datetime' => optional($event->event_datetime)?->toDateTimeString(),
                'payload_json' => $event->payload_json,
                'processing_status' => $event->processing_status,
                'processed_at' => optional($event->processed_at)?->toDateTimeString(),
                'open_failure_count' => (int) $event->open_failure_count,
                'error_message' => $event->error_message,
            ])
            ->values();

        $statusSummary = IntegrationEvent::query()
            ->tap($eventScope)
            ->selectRaw('processing_status, COUNT(*) as total')
            ->groupBy('processing_status')
            ->pluck('total', 'processing_status');

        return inertia('Apps/IntegrationEvents/Index', [
            'receivedEvents' => $receivedEvents,
            'statusSummary' => [
                'received' => (int) $statusSummary->get('received', 0),
                'validated' => (int) $statusSummary->get('validated', 0),
                'processed' => (int) $statusSummary->get('processed', 0),
                'failed' => (int) $statusSummary->get('failed', 0),
                'ignored' => (int) $statusSummary->get('ignored', 0),
            ],
        ]);
    }

    public function validateEvent(Request $request, IntegrationEvent $integrationEvent): RedirectResponse
    {
        $this->enforceCompanyAccess((int) $integrationEvent->company_id);

        if (! in_array($integrationEvent->processing_status, ['received', 'failed'], true)) {
            return back()->withErrors(['integration' => 'Event hanya bisa divalidasi ulang jika status saat ini adalah received atau failed.']);
        }

        $engine = $this->resolveValidationEngine($integrationEvent);

        if (! $engine) {
            return back()->withErrors(['integration' => 'Module/event belum didukung untuk validasi dari monitor.']);
        }

        $result = $engine->validateAndMark($integrationEvent);

        if (($result['status'] ?? null) !== 'validated') {
            return back()->withErrors([
                'integration' => 'Validasi gagal untuk event #' . $integrationEvent->id . ': ' . ($result['error'] ?? 'unknown_error'),
            ]);
        }

        return back()->with('success', 'Event #' . $integrationEvent->id . ' berhasil divalidasi.');
    }

    public function postEvent(Request $request, IntegrationEvent $integrationEvent): RedirectResponse
    {
        $this->enforceCompanyAccess((int) $integrationEvent->company_id);

        if (! in_array($integrationEvent->processing_status, ['validated', 'failed'], true)) {
            return back()->withErrors(['integration' => 'Event hanya bisa diposting/re-post jika status saat ini adalah validated atau failed.']);
        }

        $service = $this->resolvePostingService($integrationEvent);

        if (! $service) {
            return back()->withErrors(['integration' => 'Module/event belum didukung untuk posting dari monitor.']);
        }

        $result = $service->postValidatedEvent($integrationEvent);

        if (($result['status'] ?? null) === 'processed') {
            return back()->with('success', 'Event #' . $integrationEvent->id . ' berhasil diposting ke jurnal #' . $result['journal_entry_id'] . '.');
        }

        if (($result['status'] ?? null) === 'duplicate') {
            return back()->with('success', 'Event #' . $integrationEvent->id . ' sudah pernah diposting. Jurnal #' . $result['journal_entry_id'] . ' digunakan ulang.');
        }

        return back()->withErrors([
            'integration' => 'Posting gagal untuk event #' . $integrationEvent->id . ': ' . ($result['error'] ?? 'unknown_error'),
        ]);
    }

    private function resolveValidationEngine(IntegrationEvent $event): InventoryPostingRuleEngine|VendorInvoicePostingRuleEngine|ModulePresetJournalValidator|null
    {
        if (PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET) {
            return app(ModulePresetJournalValidator::class);
        }

        if ($event->source_module === 'inventory') {
            return app(InventoryPostingRuleEngine::class);
        }

        if ($event->source_module === 'accounts_payable' && $event->event_name === 'vendor.invoice.posted') {
            return app(VendorInvoicePostingRuleEngine::class);
        }

        return null;
    }

    private function resolvePostingService(IntegrationEvent $event): InventoryAutoJournalService|VendorInvoiceAutoJournalService|ModulePresetAutoJournalService|null
    {
        if (PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET) {
            return app(ModulePresetAutoJournalService::class);
        }

        if ($event->source_module === 'inventory') {
            return app(InventoryAutoJournalService::class);
        }

        if ($event->source_module === 'accounts_payable' && $event->event_name === 'vendor.invoice.posted') {
            return app(VendorInvoiceAutoJournalService::class);
        }

        return null;
    }
}
