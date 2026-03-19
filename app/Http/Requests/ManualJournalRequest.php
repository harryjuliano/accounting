<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $journalEntryId = $this->manual_journal?->id;

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
            'accounting_period_id' => ['nullable', 'exists:accounting_periods,id'],
            'journal_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('journal_entries', 'journal_no')
                    ->where(fn ($query) => $query->where('company_id', $this->company_id))
                    ->ignore($journalEntryId),
            ],
            'entry_date' => ['required', 'date'],
            'posting_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'currency_code' => ['required', 'exists:currencies,code'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0000000001'],
            'status' => ['required', 'in:draft,pending_approval,approved,posted,reversed,cancelled'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.dimension_details' => ['nullable', 'array'],
            'lines.*.dimension_details.*.dimension_id' => ['required_with:lines.*.dimension_details', 'integer', 'exists:dimensions,id'],
            'lines.*.dimension_details.*.attributes' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $totalDebit = 0;
            $totalCredit = 0;
            $lines = $this->input('lines', []);
            $accountIds = collect($lines)->pluck('account_id')->filter()->unique()->values();
            $accounts = ChartOfAccount::query()
                ->with(['dimensions:id,name,type,attribute_schema_json'])
                ->whereIn('id', $accountIds)
                ->get()
                ->keyBy('id');

            foreach ($lines as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                    $validator->errors()->add("lines.$index.debit", 'Setiap baris harus berisi debit atau kredit saja.');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;

                $account = $accounts->get((int) ($line['account_id'] ?? 0));
                if (! $account || ! $account->requires_dimension) {
                    continue;
                }

                $dimensionDetails = collect($line['dimension_details'] ?? []);

                foreach ($account->dimensions as $dimension) {
                    $detail = $dimensionDetails->first(fn ($item) => (int) ($item['dimension_id'] ?? 0) === (int) $dimension->id);

                    if (! $detail) {
                        $validator->errors()->add("lines.$index.dimension_details", "Dimensi {$dimension->name} wajib diisi untuk akun {$account->code} - {$account->name}.");
                        continue;
                    }

                    $attributes = collect($detail['attributes'] ?? []);
                    $attributeSchema = collect($dimension->attribute_schema_json ?? []);

                    foreach ($attributeSchema as $attribute) {
                        if (! ($attribute['is_required'] ?? false)) {
                            continue;
                        }

                        $key = $attribute['key'] ?? null;
                        if (! $key) {
                            continue;
                        }

                        $value = $attributes->get($key);
                        $isBlankString = is_string($value) && trim($value) === '';

                        if ($value === null || $value === '' || $isBlankString) {
                            $label = $attribute['label'] ?? $key;
                            $validator->errors()->add("lines.$index.dimension_details", "Atribut {$label} pada dimensi {$dimension->name} wajib diisi.");
                        }
                    }
                }
            }

            if ($totalDebit <= 0 || $totalCredit <= 0) {
                $validator->errors()->add('lines', 'Total debit dan kredit harus lebih dari 0.');
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add('lines', 'Total debit dan kredit harus seimbang.');
            }
        });
    }
}
