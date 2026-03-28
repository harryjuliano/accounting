<?php

namespace App\Http\Requests\Api\Integrations;

use Illuminate\Foundation\Http\FormRequest;

class InventoryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'event_name' => ['required', 'string', 'max:100'],
            'event_datetime' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'string', 'max:100'],
            'source_document_no' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'schema_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
