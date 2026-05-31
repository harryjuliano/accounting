<?php

namespace App\Http\Requests\Api\Integrations;

use Illuminate\Foundation\Http\FormRequest;

class VendorPaymentEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload', []);

        if (! is_array($payload)) {
            return;
        }

        $amounts = $payload['amounts'] ?? [];

        if (is_array($amounts)) {
            foreach (['stamp_duty', 'bank_charge', 'freight'] as $key) {
                $amounts[$key] = $amounts[$key] ?? 0;
            }

            $payload['amounts'] = $amounts;
        }

        if (! isset($payload['cash_account_id']) && isset($payload['bank_account_id'])) {
            $payload['cash_account_id'] = $payload['bank_account_id'];
        }

        $this->merge(['payload' => $payload]);
    }

    public function rules(): array
    {
        return [
            'client_key' => ['required', 'string', 'max:100'],
            'client_secret' => ['required', 'string', 'max:191'],
            'company_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'event_name' => ['required', 'string', 'max:100', 'in:vendor.payment.posted'],
            'event_datetime' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'string', 'max:100'],
            'source_document_no' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'payload.transaction_type' => ['nullable', 'string', 'max:100'],
            'payload.cash_account_id' => ['required', 'integer', 'min:1'],
            'payload.amounts' => ['required', 'array'],
            'payload.amounts.invoice_payment_total' => ['required_without:payload.invoice_lines', 'nullable', 'numeric', 'min:0'],
            'payload.amounts.withholding_tax_total' => ['nullable', 'numeric', 'min:0'],
            'payload.amounts.stamp_duty' => ['nullable', 'numeric', 'min:0'],
            'payload.amounts.bank_charge' => ['nullable', 'numeric', 'min:0'],
            'payload.amounts.freight' => ['nullable', 'numeric', 'min:0'],
            'payload.amounts.cash_out' => ['nullable', 'numeric', 'min:0'],
            'payload.invoice_lines' => ['nullable', 'array'],
            'payload.invoice_lines.*.invoice_no' => ['nullable', 'string', 'max:100'],
            'payload.invoice_lines.*.payment_amount' => ['required_with:payload.invoice_lines', 'numeric', 'min:0'],
            'payload.invoice_lines.*.withholding_tax' => ['nullable', 'numeric', 'min:0'],
            'schema_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
