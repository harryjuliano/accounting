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
        $dimension = $this->route('dimension');
        $dimensionId = is_object($dimension) ? $dimension->id : $dimension;

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
                Rule::unique('dimensions', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($dimensionId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:branch,department,cost_center,project,customer,vendor,employee,custom',
            'attribute_schema_json' => 'nullable|array',
            'attribute_schema_json.*.key' => 'required_with:attribute_schema_json|string|max:100|regex:/^[a-z][a-z0-9_]*$/',
            'attribute_schema_json.*.label' => 'required_with:attribute_schema_json|string|max:255',
            'attribute_schema_json.*.type' => 'required_with:attribute_schema_json|in:text,number,date,boolean,select',
            'attribute_schema_json.*.is_required' => 'nullable|boolean',
            'attribute_schema_json.*.options' => 'nullable|array',
            'attribute_schema_json.*.options.*' => 'required|string|max:255',
            'is_mandatory' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $attributes = $this->input('attribute_schema_json', []);
            $keys = collect($attributes)->pluck('key')->filter()->values();

            if ($keys->count() !== $keys->unique()->count()) {
                $validator->errors()->add('attribute_schema_json', 'Setiap field atribut harus memiliki key yang unik.');
            }
        });
    }
}
