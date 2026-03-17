<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $chartOfAccounts = ChartOfAccount::query()
            ->with(['company:id,name', 'accountGroup:id,name', 'parent:id,code,name'])
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('account_type', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $companies = Company::query()->select('id', 'name')->orderBy('name')->get();
        $accountGroups = AccountGroup::query()->select('id', 'company_id', 'name')->orderBy('name')->get();
        $parentAccounts = ChartOfAccount::query()->select('id', 'company_id', 'code', 'name')->orderBy('code')->get();

        return inertia('Apps/ChartOfAccounts/Index', [
            'chartOfAccounts' => $chartOfAccounts,
            'companies' => $companies,
            'accountGroups' => $accountGroups,
            'parentAccounts' => $parentAccounts,
        ]);
    }

    public function store(ChartOfAccountRequest $request)
    {
        ChartOfAccount::create($request->validated());

        return back();
    }

    public function update(ChartOfAccountRequest $request, ChartOfAccount $chart_of_account)
    {
        $chart_of_account->update($request->validated());

        return back();
    }

    public function destroy(ChartOfAccount $chart_of_account)
    {
        $chart_of_account->delete();

        return back();
    }
}
