<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Dimension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = ChartOfAccount::query()
            ->with(['company:id,name', 'accountGroup:id,name', 'parent:id,code,name', 'dimensions:id,company_id,name'])
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('account_type', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            });

        $masterChartOfAccounts = (clone $baseQuery)
            ->latest()
            ->paginate(10, ['*'], 'master_page')
            ->withQueryString();

        $transactionChartOfAccounts = (clone $baseQuery)
            ->where('level', 4)
            ->latest()
            ->paginate(10, ['*'], 'transaction_page')
            ->withQueryString();

        $companies = Company::query()->select('id', 'name')->orderBy('name')->get();
        $accountGroups = AccountGroup::query()->select('id', 'company_id', 'name')->orderBy('name')->get();
        $parentAccounts = ChartOfAccount::query()
            ->select('id', 'company_id', 'account_group_id', 'code', 'name', 'level', 'account_type', 'financial_statement_group')
            ->where('level', 3)
            ->orderBy('code')
            ->get();
        $dimensions = Dimension::query()->select('id', 'company_id', 'name')->where('is_active', true)->orderBy('name')->get();

        return inertia('Apps/ChartOfAccounts/Index', [
            'masterChartOfAccounts' => $masterChartOfAccounts,
            'transactionChartOfAccounts' => $transactionChartOfAccounts,
            'companies' => $companies,
            'accountGroups' => $accountGroups,
            'parentAccounts' => $parentAccounts,
            'dimensions' => $dimensions,
        ]);
    }

    public function store(ChartOfAccountRequest $request)
    {
        $payload = $request->validated();
        $this->applyTransactionDefaults($payload);
        $dimensionIds = $payload['dimension_ids'] ?? [];
        unset($payload['dimension_ids']);

        $this->validateDimensionCompany($payload['company_id'], $dimensionIds);

        DB::transaction(function () use ($payload, $dimensionIds) {
            $chartOfAccount = ChartOfAccount::create($payload);
            $chartOfAccount->dimensions()->sync($dimensionIds);
        });

        return back();
    }

    public function update(ChartOfAccountRequest $request, ChartOfAccount $chart_of_account)
    {
        $payload = $request->validated();
        $this->applyTransactionDefaults($payload);
        $dimensionIds = $payload['dimension_ids'] ?? [];
        unset($payload['dimension_ids']);

        $this->validateDimensionCompany($payload['company_id'], $dimensionIds);

        DB::transaction(function () use ($chart_of_account, $payload, $dimensionIds) {
            $chart_of_account->update($payload);
            $chart_of_account->dimensions()->sync($dimensionIds);
        });

        return back();
    }

    public function destroy(ChartOfAccount $chart_of_account)
    {
        $chart_of_account->delete();

        return back();
    }

    private function validateDimensionCompany(int $companyId, array $dimensionIds): void
    {
        if (empty($dimensionIds)) {
            return;
        }

        $invalidExists = Dimension::query()
            ->whereIn('id', $dimensionIds)
            ->where('company_id', '!=', $companyId)
            ->exists();

        if ($invalidExists) {
            throw ValidationException::withMessages([
                'dimension_ids' => 'Semua dimension harus berasal dari company yang sama dengan akun COA.',
            ]);
        }
    }

    private function applyTransactionDefaults(array &$payload): void
    {
        if (($payload['form_type'] ?? null) !== 'transaction') {
            unset($payload['form_type']);

            return;
        }

        $parentAccount = ChartOfAccount::query()
            ->select('id', 'account_group_id', 'account_type', 'financial_statement_group')
            ->find($payload['parent_id']);

        $payload['level'] = 4;
        $payload['account_group_id'] = $parentAccount?->account_group_id;
        $payload['account_type'] = $parentAccount?->account_type;
        $payload['financial_statement_group'] = $parentAccount?->financial_statement_group;

        unset($payload['form_type']);
    }
}
