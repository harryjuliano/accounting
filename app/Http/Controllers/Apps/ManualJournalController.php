<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualJournalRequest;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualJournalController extends Controller
{
    use InteractsWithCompanyScope;

    private function buildJournalLinePayload(array $line, int $lineNo, string $currencyCode, float $exchangeRate): array
    {
        $debit = (float) ($line['debit'] ?? 0);
        $credit = (float) ($line['credit'] ?? 0);
        $originalAmount = $debit > 0 ? $debit : $credit;

        return [
            'line_no' => $lineNo,
            'account_id' => $line['account_id'],
            'description' => $line['description'] ?? null,
            'debit' => $debit,
            'credit' => $credit,
            'original_currency_code' => $currencyCode,
            'original_currency_amount' => $originalAmount,
            'base_currency_debit' => round($debit * $exchangeRate, 2),
            'base_currency_credit' => round($credit * $exchangeRate, 2),
            'dimension_details_json' => $line['dimension_details'] ?? null,
        ];
    }

    private function resolveAccountingPeriod(int $companyId, string $postingDate): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->with('fiscalYear:id,status')
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $postingDate)
            ->whereDate('end_date', '>=', $postingDate)
            ->first();
    }

    private function ensurePeriodAllowsPosting(AccountingPeriod $accountingPeriod): void
    {
        if ($accountingPeriod->fiscalYear?->status === 'closed') {
            throw ValidationException::withMessages([
                'posting_date' => 'Tahun fiskal sudah hard close. Posting jurnal untuk tahun ini tidak diizinkan.',
            ]);
        }

        if ($accountingPeriod->status !== 'open') {
            throw ValidationException::withMessages([
                'posting_date' => 'Periode bulanan sudah soft/hard close. Posting jurnal pada periode ini tidak diizinkan.',
            ]);
        }
    }

    public function index(Request $request)
    {
        $manualJournals = JournalEntry::query()
            ->with(['company:id,name', 'branch:id,company_id,code,name', 'accountingPeriod:id,period_name', 'lines.account:id,company_id,code,name,requires_dimension'])
            ->where('journal_type', 'manual')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
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
            'companies' => $this->getAccessibleCompanies(),
            'branches' => Branch::query()
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->orderBy('code')
                ->get(),
            'accountingPeriods' => AccountingPeriod::query()->select('id', 'company_id', 'period_name', 'start_date', 'end_date')
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->orderByDesc('start_date')->get(),
            'currencies' => Currency::query()->select('code', 'name', 'decimal_places')->where('is_active', true)->orderBy('code')->get(),
            'accounts' => ChartOfAccount::query()
                ->select('id', 'company_id', 'code', 'name', 'level', 'requires_dimension')
                ->with(['dimensions:id,company_id,name,type,attribute_schema_json'])
                ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
                ->where('is_active', true)
                ->where('level', 4)
                ->orderBy('code')
                ->get(),
            'defaultEntryDate' => Carbon::now()->toDateString(),
        ]);
    }

    public function store(ManualJournalRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $accountingPeriod = $this->resolveAccountingPeriod((int) $validated['company_id'], $validated['posting_date']);

            if (! $accountingPeriod) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
                ]);
            }
            $this->ensurePeriodAllowsPosting($accountingPeriod);

            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $journalEntry = JournalEntry::create([
                'company_id' => $validated['company_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'accounting_period_id' => $accountingPeriod->id,
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
                $journalEntry->lines()->create(
                    $this->buildJournalLinePayload(
                        $line,
                        $index + 1,
                        (string) $validated['currency_code'],
                        (float) $validated['exchange_rate']
                    )
                );
            }
        });

        return back();
    }

    public function update(ManualJournalRequest $request, JournalEntry $manual_journal)
    {
        $this->enforceCompanyAccess((int) $manual_journal->company_id);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $manual_journal) {
            $accountingPeriod = $this->resolveAccountingPeriod((int) $validated['company_id'], $validated['posting_date']);

            if (! $accountingPeriod) {
                throw ValidationException::withMessages([
                    'posting_date' => 'Periode fiskal untuk tanggal posting tidak ditemukan.',
                ]);
            }
            $this->ensurePeriodAllowsPosting($accountingPeriod);

            $totalDebit = collect($validated['lines'])->sum(fn ($line) => (float) $line['debit']);
            $totalCredit = collect($validated['lines'])->sum(fn ($line) => (float) $line['credit']);

            $manual_journal->update([
                'company_id' => $validated['company_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'accounting_period_id' => $accountingPeriod->id,
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
                $manual_journal->lines()->create(
                    $this->buildJournalLinePayload(
                        $line,
                        $index + 1,
                        (string) $validated['currency_code'],
                        (float) $validated['exchange_rate']
                    )
                );
            }
        });

        return back();
    }

    public function destroy(JournalEntry $manual_journal)
    {
        $this->enforceCompanyAccess((int) $manual_journal->company_id);
        $manual_journal->delete();

        return back();
    }
}
