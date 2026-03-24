<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualJournalRequest;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceController extends Controller
{
    use InteractsWithCompanyScope;

    public function store(ManualJournalRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $accountingPeriod = AccountingPeriod::query()
                ->with('fiscalYear:id,start_date,status')
                ->where('company_id', $validated['company_id'])
                ->whereDate('start_date', '<=', $validated['posting_date'])
                ->whereDate('end_date', '>=', $validated['posting_date'])
                ->first();

            if (! $accountingPeriod) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
                ]);
            }

            if ((int) $accountingPeriod->period_no !== 1) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Saldo awal hanya boleh diposting pada periode Januari (periode 1).',
                ]);
            }

            if ($accountingPeriod->fiscalYear?->status === 'closed' || $accountingPeriod->status !== 'open') {
                throw ValidationException::withMessages([
                    'posting_date' => 'Periode/fiscal year sudah ditutup. Input saldo awal tidak diizinkan.',
                ]);
            }

            if ((string) $validated['posting_date'] !== (string) optional($accountingPeriod->fiscalYear?->start_date)->toDateString()) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Tanggal posting saldo awal harus sama dengan tanggal awal tahun fiskal.',
                ]);
            }

            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $journalEntry = JournalEntry::create([
                'company_id' => $validated['company_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'accounting_period_id' => $accountingPeriod->id,
                'journal_no' => $validated['journal_no'],
                'journal_type' => 'opening',
                'source_module' => 'opening_balance_manual',
                'source_event' => 'manual_input',
                'source_document_type' => 'opening_balance',
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
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);
                $originalAmount = $debit > 0 ? $debit : $credit;

                $journalEntry->lines()->create([
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                    'original_currency_code' => $validated['currency_code'],
                    'original_currency_amount' => $originalAmount,
                    'base_currency_debit' => round($debit * (float) $validated['exchange_rate'], 2),
                    'base_currency_credit' => round($credit * (float) $validated['exchange_rate'], 2),
                    'dimension_details_json' => $line['dimension_details'] ?? null,
                ]);
            }
        });

        return back();
    }
}
