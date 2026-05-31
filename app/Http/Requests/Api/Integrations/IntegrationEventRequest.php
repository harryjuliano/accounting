<?php

namespace App\Http\Requests\Api\Integrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntegrationEventRequest extends FormRequest
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
            'source_module' => ['required', 'string', 'max:100'],
            'event_name' => ['required', 'string', 'max:100'],
            'event_datetime' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'string', 'max:100'],
            'source_document_no' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'payload.posting_mode' => ['nullable', Rule::in(['rule', 'module_preset'])],
            'payload.journal' => ['required_if:payload.posting_mode,module_preset', 'array'],
            'payload.journal.lines' => ['required_if:payload.posting_mode,module_preset', 'array', 'min:2'],
            'payload.journal.lines.*.line_no' => ['nullable', 'integer', 'min:1'],
            'payload.journal.lines.*.line_side' => ['required_if:payload.posting_mode,module_preset', Rule::in(['debit', 'credit'])],
            'payload.journal.lines.*.account_id' => ['nullable', 'integer'],
            'payload.journal.lines.*.account_code' => ['nullable', 'string', 'max:100'],
            'payload.journal.lines.*.amount' => ['required_if:payload.posting_mode,module_preset', 'numeric', 'min:0.01'],
            'payload.journal.lines.*.description' => ['nullable', 'string', 'max:191'],
            'payload.journal.lines.*.description_template' => ['nullable', 'string', 'max:191'],
            'payload.journal.lines.*.dimensions' => ['nullable', 'array'],
            'payload.journal.lines.*.dimension_details' => ['nullable', 'array'],
            'schema_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
