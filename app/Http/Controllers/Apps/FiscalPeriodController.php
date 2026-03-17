<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalPeriodRequest;
use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Http\Request;

class FiscalPeriodController extends Controller
{
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
        FiscalYear::create($request->validated());

        return back();
    }

    public function update(FiscalPeriodRequest $request, FiscalYear $fiscal_period)
    {
        $fiscal_period->update($request->validated());

        return back();
    }

    public function destroy(FiscalYear $fiscal_period)
    {
        $fiscal_period->delete();

        return back();
    }
}
