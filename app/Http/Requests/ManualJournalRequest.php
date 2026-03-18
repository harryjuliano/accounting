<?php

namespace App\Http\Requests;

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
            'company_id' => ['required', 'exists:companies,id'],
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($this->input('lines', []) as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                    $validator->errors()->add("lines.$index.debit", 'Setiap baris harus berisi debit atau kredit saja.');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
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
