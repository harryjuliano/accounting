<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DashboardController extends Controller implements HasMiddleware
{
    use InteractsWithCompanyScope;

    /**
     * middleware
     */
    public static function middleware()
    {
        return [
            new Middleware('permission:dashboard-access|dashboard-data'),
        ];
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $timezone = $request->user()?->company?->timezone ?? config('app.timezone', 'UTC');
        $today = Carbon::now($timezone)->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $yearStart = $today->copy()->startOfYear();

        $entryScope = fn (Builder $query) => $query
            ->when(
                $this->isCompanyAdmin(),
                fn (Builder $builder) => $builder->where('journal_entries.company_id', $request->user()->company_id)
            );

        $allEntries = JournalEntry::query()->tap($entryScope);

        $statusCounts = (clone $allEntries)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $periodSnapshot = AccountingPeriod::query()
            ->when(
                $this->isCompanyAdmin(),
                fn (Builder $builder) => $builder->where('company_id', $request->user()->company_id)
            )
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $baseLineQuery = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->join('account_groups', 'account_groups.id', '=', 'chart_of_accounts.account_group_id')
            ->tap($entryScope)
            ->where('journal_entries.status', 'posted');

        $balanceRows = (clone $baseLineQuery)
            ->whereDate('journal_entries.posting_date', '<=', $monthEnd->toDateString())
            ->whereNotIn('journal_entries.journal_type', ['closing'])
            ->whereIn('account_groups.type', ['asset', 'liability', 'equity'])
            ->selectRaw('account_groups.type as account_type')
            ->selectRaw('chart_of_accounts.normal_balance as normal_balance')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
            ->groupBy('account_groups.type', 'chart_of_accounts.normal_balance')
            ->get();

        $totalAsset = 0.0;
        $totalLiability = 0.0;
        $totalEquity = 0.0;

        foreach ($balanceRows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $amount = $row->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

            if ($row->account_type === 'asset') {
                $totalAsset += $amount;
            }

            if ($row->account_type === 'liability') {
                $totalLiability += $amount;
            }

            if ($row->account_type === 'equity') {
                $totalEquity += $amount;
            }
        }

        $profitRows = (clone $baseLineQuery)
            ->whereDate('journal_entries.posting_date', '>=', $yearStart->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $monthEnd->toDateString())
            ->whereNotIn('journal_entries.journal_type', ['opening', 'closing'])
            ->whereIn('account_groups.type', ['revenue', 'cogs', 'expense', 'other_income', 'other_expense'])
            ->selectRaw('account_groups.type as account_type')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_credit), 0) as credit')
            ->groupBy('account_groups.type')
            ->get();

        $netProfitYtd = 0.0;
        foreach ($profitRows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $netProfitYtd += $credit - $debit;
        }

        $monthlyPostedAmount = (float) (clone $allEntries)
            ->where('status', 'posted')
            ->whereDate('posting_date', '>=', $monthStart->toDateString())
            ->whereDate('posting_date', '<=', $monthEnd->toDateString())
            ->sum('total_debit');

        $topExpenseAccounts = (clone $baseLineQuery)
            ->whereDate('journal_entries.posting_date', '>=', $monthStart->toDateString())
            ->whereDate('journal_entries.posting_date', '<=', $monthEnd->toDateString())
            ->whereIn('account_groups.type', ['expense', 'cogs', 'other_expense'])
            ->selectRaw('chart_of_accounts.code as coa_code')
            ->selectRaw('chart_of_accounts.name as coa_name')
            ->selectRaw('COALESCE(SUM(journal_lines.base_currency_debit - journal_lines.base_currency_credit), 0) as amount')
            ->groupBy('chart_of_accounts.code', 'chart_of_accounts.name')
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'coa_code' => $row->coa_code,
                'coa_name' => $row->coa_name,
                'amount' => (float) $row->amount,
            ])
            ->values();

        $integrationQueue = (clone $allEntries)
            ->whereNotNull('source_module')
            ->whereNotNull('source_event')
            ->whereDate('posting_date', '>=', $monthStart->toDateString())
            ->whereDate('posting_date', '<=', $monthEnd->toDateString())
            ->selectRaw('source_module')
            ->selectRaw('source_event')
            ->selectRaw("SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted")
            ->selectRaw("SUM(CASE WHEN status IN ('cancelled', 'reversed') THEN 1 ELSE 0 END) as failed")
            ->groupBy('source_module', 'source_event')
            ->orderByDesc('posted')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source_module,
                'event' => $row->source_event,
                'posted' => (int) $row->posted,
                'failed' => (int) $row->failed,
            ])
            ->values();

        return inertia('Apps/Dashboard', [
            'kpis' => [
                'total_asset' => $totalAsset,
                'total_liability' => $totalLiability,
                'total_equity' => $totalEquity,
                'net_profit_ytd' => $netProfitYtd,
                'monthly_posted_amount' => $monthlyPostedAmount,
                'monthly_posted_entries' => (int) ((clone $allEntries)
                    ->where('status', 'posted')
                    ->whereDate('posting_date', '>=', $monthStart->toDateString())
                    ->whereDate('posting_date', '<=', $monthEnd->toDateString())
                    ->count()),
                'monthly_draft_entries' => (int) ((clone $allEntries)
                    ->whereIn('status', ['draft', 'pending_approval', 'approved'])
                    ->whereDate('posting_date', '>=', $monthStart->toDateString())
                    ->whereDate('posting_date', '<=', $monthEnd->toDateString())
                    ->count()),
            ],
            'statusSummary' => [
                'posted' => (int) ($statusCounts->get('posted', 0)),
                'pending_approval' => (int) ($statusCounts->get('pending_approval', 0)),
                'draft' => (int) ($statusCounts->get('draft', 0)),
                'reversed' => (int) ($statusCounts->get('reversed', 0)),
                'cancelled' => (int) ($statusCounts->get('cancelled', 0)),
            ],
            'periodSummary' => [
                'open' => (int) ($periodSnapshot->get('open', 0)),
                'soft_closed' => (int) ($periodSnapshot->get('soft_closed', 0)),
                'hard_closed' => (int) ($periodSnapshot->get('hard_closed', 0)),
                'audit_closed' => (int) ($periodSnapshot->get('audit_closed', 0)),
            ],
            'topExpenseAccounts' => $topExpenseAccounts,
            'integrationQueue' => $integrationQueue,
            'asOfDate' => $today->toDateString(),
        ]);
    }
}
