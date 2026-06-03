<?php

namespace App\Http\Requests\Api\Integrations;

use Illuminate\Foundation\Http\FormRequest;

class UniversalJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'journal_type' => $this->input('journal_type', 'auto'),
            'status' => $this->input('status', 'posted'),
            'exchange_rate' => $this->input('exchange_rate', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_key' => ['required', 'string', 'max:100'],
            'client_secret' => ['required', 'string', 'max:191'],
            'company_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'company_code' => ['nullable', 'string', 'max:100'],
            'branch_code' => ['nullable', 'string', 'max:100'],
            'integration_key' => ['required', 'string', 'max:191'],
            'journal_no' => ['nullable', 'string', 'max:255'],
            'journal_type' => ['required', 'in:manual,auto,adjustment,reversing,opening,closing'],
            'status' => ['required', 'in:draft,pending_approval,approved,posted,reversed,cancelled'],
            'source_module' => ['required', 'string', 'max:100'],
            'source_module_name' => ['nullable', 'string', 'max:255'],
            'source_event' => ['nullable', 'string', 'max:100'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'string', 'max:100'],
            'source_document_no' => ['nullable', 'string', 'max:100'],
            'entry_date' => ['required', 'date'],
            'posting_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'counterparty_type' => ['nullable', 'string', 'max:255'],
            'counterparty_code' => ['nullable', 'string', 'max:255'],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'salesperson_code' => ['nullable', 'string', 'max:255'],
            'salesperson_name' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', 'exists:currencies,code'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0000000001'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.line_no' => ['nullable', 'integer', 'min:1'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.account_code' => ['nullable', 'string', 'max:100'],
            'lines.*.description' => ['nullable', 'string', 'max:191'],
            'lines.*.item_code' => ['nullable', 'string', 'max:255'],
            'lines.*.item_name' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity_uom' => ['nullable', 'string', 'max:50'],
            'lines.*.cost_center_code' => ['nullable', 'string', 'max:255'],
            'lines.*.cost_center_name' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.dimensions' => ['nullable', 'array'],
            'lines.*.dimension_details' => ['nullable', 'array'],
            'schema_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
