<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
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

        return inertia('Apps/IntegrationJournals/Index', [
            'autoJournals' => $autoJournals,
        ]);
    }
}
