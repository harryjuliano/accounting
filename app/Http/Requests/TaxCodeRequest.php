<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taxCodeId = $this->tax_code?->id;

        return [
            'company_id' => 'required|exists:companies,id',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tax_codes', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($taxCodeId),
            ],
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'tax_type' => 'required|in:sales,purchase,withholding,other',
            'is_inclusive' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }
}
