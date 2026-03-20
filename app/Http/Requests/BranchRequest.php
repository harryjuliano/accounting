<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branch = $this->route('branch');
        $branchId = is_object($branch) ? $branch->id : $branch;

        return [
            'company_id' => [
                'required',
                'exists:companies,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->user()?->hasRole('company-admin') && (int) $this->user()->company_id !== (int) $value) {
                        $fail('Anda tidak memiliki akses ke company ini.');
                    }
                },
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($branchId),
            ],
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ];
    }
}
