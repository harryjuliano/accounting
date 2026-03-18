<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fiscalYearId = $this->fiscal_period?->id;

        return [
            'company_id' => 'required|exists:companies,id',
            'year_label' => [
                'required',
                'digits:4',
                Rule::unique('fiscal_years', 'year_label')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($fiscalYearId),
            ],
            'status' => 'required|in:draft,open,closed',
        ];
    }
}
