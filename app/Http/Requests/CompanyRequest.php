<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->company?->id;

        return [
            'code' => 'required|string|max:255|unique:companies,code,' . $companyId,
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'base_currency_code' => 'required|string|size:3',
            'country_code' => 'required|string|size:2',
            'timezone' => 'required|string|max:255',
            'fiscal_year_start_month' => 'required|integer|between:1,12',
            'is_active' => 'required|boolean',
        ];
    }
}
