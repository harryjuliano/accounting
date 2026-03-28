<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class IntegrationJournalController extends Controller implements HasMiddleware
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

        $journalScope = fn (Builder $query) => $query
            ->when(
                $this->isCompanyAdmin() && $companyId,
                fn (Builder $builder) => $builder->where('journal_entries.company_id', $companyId)
            );

        $autoJournals = JournalEntry::query()
            ->tap($journalScope)
            ->where('journal_entries.journal_type', 'auto')
            ->whereNotNull('journal_entries.integration_key')
            ->select([
                'journal_entries.id',
                'journal_entries.journal_no',
                'journal_entries.source_module',
                'journal_entries.source_event',
                'journal_entries.source_document_no',
                'journal_entries.integration_key',
                'journal_entries.posting_date',
                'journal_entries.status',
                'journal_entries.total_debit',
                'journal_entries.currency_code',
                'journal_entries.created_at',
            ])
            ->orderByDesc('journal_entries.created_at')
            ->limit(100)
            ->get()
            ->map(fn (JournalEntry $journal) => [
                'id' => $journal->id,
                'journal_no' => $journal->journal_no,
                'source_module' => $journal->source_module,
                'source_event' => $journal->source_event,
                'source_document_no' => $journal->source_document_no,
                'integration_key' => $journal->integration_key,
                'posting_date' => $journal->posting_date,
                'status' => $journal->status,
                'total_debit' => (float) $journal->total_debit,
                'currency_code' => $journal->currency_code,
                'created_at' => optional($journal->created_at)?->toDateTimeString(),
            ])
            ->values();

        $eventQuery = IntegrationEvent::query()
            ->tap($eventScope)
            ->withCount([
                'failures as open_failure_count' => fn (Builder $query) => $query->whereNull('resolved_at'),
            ])
            ->select([
                'id',
                'source_module',
                'event_name',
                'source_document_type',
                'source_document_no',
                'idempotency_key',
                'event_datetime',
                'processing_status',
                'processed_at',
                'error_message',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(100);

        $receivedEvents = $eventQuery
            ->get()
            ->map(fn (IntegrationEvent $event) => [
                'id' => $event->id,
                'source_module' => $event->source_module,
                'event_name' => $event->event_name,
                'source_document_type' => $event->source_document_type,
                'source_document_no' => $event->source_document_no,
                'idempotency_key' => $event->idempotency_key,
                'event_datetime' => optional($event->event_datetime)?->toDateTimeString(),
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

        return inertia('Apps/IntegrationJournals/Index', [
            'autoJournals' => $autoJournals,
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
}
