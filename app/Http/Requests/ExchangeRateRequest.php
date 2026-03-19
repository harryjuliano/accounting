<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $exchangeRateId = $this->exchange_rate?->id;

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
            'rate_date' => 'required|date',
            'from_currency_code' => 'required|string|size:3|exists:currencies,code',
            'to_currency_code' => 'required|string|size:3|different:from_currency_code|exists:currencies,code',
            'rate' => 'required|numeric|gt:0',
            'rate_type' => 'required|in:spot,month_end,average,custom',
            'source' => 'nullable|string|max:255',
            'composite_unique' => [
                Rule::unique('exchange_rates', 'company_id')
                    ->where(fn ($query) => $query
                        ->where('rate_date', $this->rate_date)
                        ->where('from_currency_code', $this->from_currency_code)
                        ->where('to_currency_code', $this->to_currency_code)
                        ->where('rate_type', $this->rate_type)
                    )
                    ->ignore($exchangeRateId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from_currency_code' => strtoupper((string) $this->from_currency_code),
            'to_currency_code' => strtoupper((string) $this->to_currency_code),
            'composite_unique' => $this->company_id,
        ]);
    }
}
