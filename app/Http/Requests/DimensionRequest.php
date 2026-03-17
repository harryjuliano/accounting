<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dimensionId = $this->dimension?->id;

        return [
            'company_id' => 'required|exists:companies,id',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dimensions', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($dimensionId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:branch,department,cost_center,project,customer,vendor,employee,custom',
            'is_mandatory' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }
}
