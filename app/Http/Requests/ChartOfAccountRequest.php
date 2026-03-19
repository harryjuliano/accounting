<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $chartOfAccountId = $this->chart_of_account?->id;
        $isTransactionCoa = $this->input('form_type') === 'transaction';

        return [
            'form_type' => 'nullable|in:master,transaction',
            'company_id' => 'required|exists:companies,id',
            'account_group_id' => 'nullable|exists:account_groups,id',
            'parent_id' => [
                Rule::requiredIf($isTransactionCoa),
                'nullable',
                'exists:chart_of_accounts,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($isTransactionCoa) {
                    if (! $isTransactionCoa || empty($value)) {
                        return;
                    }

                    $parentAccount = \App\Models\ChartOfAccount::query()
                        ->select('id', 'company_id', 'level')
                        ->find($value);

                    if (! $parentAccount || (int) $parentAccount->level !== 3) {
                        $fail('Parent wajib berasal dari Master COA level 3.');
                    }

                    if ($parentAccount && (int) $parentAccount->company_id !== (int) $this->company_id) {
                        $fail('Parent harus berasal dari company yang sama.');
                    }
                },
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chart_of_accounts', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($chartOfAccountId),
            ],
            'name' => 'required|string|max:255',
            'alias_name' => 'nullable|string|max:255',
            'level' => $isTransactionCoa
                ? 'required|integer|in:4'
                : 'required|integer|min:1|max:10',
            'account_type' => 'required|string|max:255',
            'normal_balance' => 'required|in:debit,credit',
            'financial_statement_group' => 'required|string|max:255',
            'cashflow_group' => 'nullable|string|max:255',
            'allow_manual_posting' => 'required|boolean',
            'allow_reconciliation' => 'required|boolean',
            'requires_dimension' => 'required|boolean',
            'dimension_ids' => 'nullable|array',
            'dimension_ids.*' => 'integer|exists:dimensions,id',
            'is_control_account' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }
}
