<?php

namespace App\Http\Requests\Api\Integrations;

use Illuminate\Foundation\Http\FormRequest;

class VendorInvoiceEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_key' => ['required', 'string', 'max:100'],
            'client_secret' => ['required', 'string', 'max:191'],
            'company_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'event_name' => ['required', 'string', 'max:100'],
            'event_datetime' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'string', 'max:100'],
            'source_document_no' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'payload.transaction_type' => ['nullable', 'string', 'max:100'],
            'payload.amounts' => ['required', 'array'],
            'payload.amounts.invoice' => ['required', 'numeric', 'min:0'],
            'payload.amounts.tax' => ['required', 'numeric', 'min:0'],
            'payload.amounts.freight' => ['required', 'numeric', 'min:0'],
            'payload.amounts.withholding_tax' => ['required', 'numeric', 'min:0'],
            'payload.amounts.payable_total' => ['required', 'numeric', 'min:0'],
            'schema_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
