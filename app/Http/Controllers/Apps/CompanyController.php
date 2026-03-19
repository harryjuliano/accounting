<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request)
    {
        $companies = Company::query()
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('id', $request->user()->company_id))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('legal_name', 'like', '%' . $request->search . '%');
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function store(CompanyRequest $request)
    {
        abort_if($this->isCompanyAdmin(), 403, 'Company admin tidak dapat membuat company baru.');

        Company::create($request->validated());

        return back();
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $this->enforceCompanyAccess((int) $company->id);
        $company->update($request->validated());

        return back();
    }

    public function destroy(Company $company)
    {
        $this->enforceCompanyAccess((int) $company->id);
        $company->delete();

        return back();
    }
}
