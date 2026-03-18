<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyCode = $this->currency?->code;

        return [
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')->ignore($currencyCode, 'code'),
            ],
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:10',
            'decimal_places' => 'required|integer|min:0|max:6',
            'is_active' => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->code),
        ]);
    }
}
