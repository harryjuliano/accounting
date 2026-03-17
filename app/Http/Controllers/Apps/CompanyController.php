<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
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
        Company::create($request->validated());

        return back();
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        return back();
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return back();
    }
}
