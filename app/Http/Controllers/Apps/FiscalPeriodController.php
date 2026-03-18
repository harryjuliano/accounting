<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalPeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FiscalPeriodController extends Controller
{
    private function buildFiscalYearPayload(array $validated): array
    {
        $fiscalYear = (int) $validated['year_label'];

        return [
            'company_id' => (int) $validated['company_id'],
            'year_label' => (string) $validated['year_label'],
            'start_date' => Carbon::create($fiscalYear, 1, 1)->startOfDay()->toDateString(),
            'end_date' => Carbon::create($fiscalYear, 12, 31)->endOfDay()->toDateString(),
            'status' => $validated['status'],
        ];
    }

    private function syncAccountingPeriods(FiscalYear $fiscalYear): void
    {
        $ranges = CarbonPeriod::create(
            Carbon::parse($fiscalYear->start_date)->startOfMonth(),
            '1 month',
            Carbon::parse($fiscalYear->end_date)->startOfMonth(),
        );

        $rows = [];

        foreach ($ranges as $index => $startDate) {
            $rows[] = [
                'company_id' => $fiscalYear->company_id,
                'fiscal_year_id' => $fiscalYear->id,
                'period_no' => $index + 1,
                'period_name' => $startDate->translatedFormat('F Y'),
                'start_date' => $startDate->copy()->startOfMonth()->toDateString(),
                'end_date' => $startDate->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        AccountingPeriod::upsert(
            $rows,
            ['company_id', 'fiscal_year_id', 'period_no'],
            ['period_name', 'start_date', 'end_date', 'updated_at'],
        );
    }

    public function index(Request $request)
    {
        $fiscalPeriods = FiscalYear::query()
            ->with('company:id,name')
            ->when($request->search, fn ($query) => $query->where('year_label', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $companies = Company::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return inertia('Apps/FiscalPeriods/Index', [
            'fiscalPeriods' => $fiscalPeriods,
            'companies' => $companies,
        ]);
    }

    public function store(FiscalPeriodRequest $request)
    {
        DB::transaction(function () use ($request) {
            $fiscalYear = FiscalYear::create($this->buildFiscalYearPayload($request->validated()));
            $this->syncAccountingPeriods($fiscalYear);
        });

        return back();
    }

    public function update(FiscalPeriodRequest $request, FiscalYear $fiscal_period)
    {
        DB::transaction(function () use ($request, $fiscal_period) {
            $fiscal_period->update($this->buildFiscalYearPayload($request->validated()));
            $this->syncAccountingPeriods($fiscal_period->fresh());
        });

        return back();
    }

    public function destroy(FiscalYear $fiscal_period)
    {
        $fiscal_period->delete();

        return back();
    }
}
