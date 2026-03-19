<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaxCodeRequest;
use App\Models\Company;
use App\Models\TaxCode;
use Illuminate\Http\Request;

class TaxCodeController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request)
    {
        $taxCodes = TaxCode::query()
            ->with('company:id,name')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('tax_type', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $companies = $this->getAccessibleCompanies();

        return inertia('Apps/TaxCodes/Index', [
            'taxCodes' => $taxCodes,
            'companies' => $companies,
        ]);
    }

    public function store(TaxCodeRequest $request)
    {
        TaxCode::create($request->validated());

        return back();
    }

    public function update(TaxCodeRequest $request, TaxCode $tax_code)
    {
        $this->enforceCompanyAccess((int) $tax_code->company_id);
        $tax_code->update($request->validated());

        return back();
    }

    public function destroy(TaxCode $tax_code)
    {
        $this->enforceCompanyAccess((int) $tax_code->company_id);
        $tax_code->delete();

        return back();
    }
}
