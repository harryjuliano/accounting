<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request)
    {
        $branches = Branch::query()
            ->with('company:id,name')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('city', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $companies = $this->getAccessibleCompanies();

        return inertia('Apps/Branches/Index', [
            'branches' => $branches,
            'companies' => $companies,
        ]);
    }

    public function store(BranchRequest $request)
    {
        Branch::create($request->validated());

        return back();
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $this->enforceCompanyAccess((int) $branch->company_id);
        $branch->update($request->validated());

        return back();
    }

    public function destroy(Branch $branch)
    {
        $this->enforceCompanyAccess((int) $branch->company_id);
        $branch->delete();

        return back();
    }
}
