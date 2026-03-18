<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualJournalRequest;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualJournalController extends Controller
{
    public function index(Request $request)
    {
        $manualJournals = JournalEntry::query()
            ->with(['company:id,name', 'accountingPeriod:id,period_name', 'lines.account:id,code,name'])
            ->where('journal_type', 'manual')
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('journal_no', 'like', '%' . $request->search . '%')
                        ->orWhere('reference_no', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/ManualJournals/Index', [
            'manualJournals' => $manualJournals,
            'companies' => Company::query()->select('id', 'name')->orderBy('name')->get(),
            'accountingPeriods' => AccountingPeriod::query()->select('id', 'company_id', 'period_name', 'start_date', 'end_date')->orderByDesc('start_date')->get(),
            'currencies' => Currency::query()->select('code', 'name')->where('is_active', true)->orderBy('code')->get(),
            'accounts' => ChartOfAccount::query()->select('id', 'company_id', 'code', 'name')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(ManualJournalRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $journalEntry = JournalEntry::create([
                'company_id' => $validated['company_id'],
                'accounting_period_id' => $validated['accounting_period_id'],
                'journal_no' => $validated['journal_no'],
                'journal_type' => 'manual',
                'entry_date' => $validated['entry_date'],
                'posting_date' => $validated['posting_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'description' => $validated['description'],
                'currency_code' => $validated['currency_code'],
                'exchange_rate' => $validated['exchange_rate'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $validated['status'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['lines'] as $index => $line) {
                $journalEntry->lines()->create([
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'base_currency_debit' => $line['debit'],
                    'base_currency_credit' => $line['credit'],
                ]);
            }
        });

        return back();
    }

    public function update(ManualJournalRequest $request, JournalEntry $manual_journal)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $manual_journal) {
            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $manual_journal->update([
                'company_id' => $validated['company_id'],
                'accounting_period_id' => $validated['accounting_period_id'],
                'journal_no' => $validated['journal_no'],
                'entry_date' => $validated['entry_date'],
                'posting_date' => $validated['posting_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'description' => $validated['description'],
                'currency_code' => $validated['currency_code'],
                'exchange_rate' => $validated['exchange_rate'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $validated['status'],
            ]);

            $manual_journal->lines()->delete();

            foreach ($validated['lines'] as $index => $line) {
                $manual_journal->lines()->create([
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'base_currency_debit' => $line['debit'],
                    'base_currency_credit' => $line['credit'],
                ]);
            }
        });

        return back();
    }

    public function destroy(JournalEntry $manual_journal)
    {
        $manual_journal->delete();

        return back();
    }
}
