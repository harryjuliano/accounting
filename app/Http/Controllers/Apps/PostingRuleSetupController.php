<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\CoaMapping;
use App\Models\PostingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostingRuleSetupController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request): Response
    {
        $rules = $this->scopeCompany(PostingRule::query())
            ->with(['lines' => fn ($query) => $query->orderBy('line_no')])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $companyIds = collect($rules->items())->pluck('company_id')->unique()->values();
        $moduleNames = collect($rules->items())->pluck('module_name')->unique()->filter()->values();

        $mappings = CoaMapping::query()
            ->whereIn('company_id', $companyIds)
            ->whereIn('module_name', $moduleNames)
            ->select('id', 'company_id', 'module_name', 'mapping_key', 'account_id', 'description')
            ->with('account:id,code,name')
            ->get()
            ->groupBy(fn ($mapping) => $mapping->company_id.'|'.$mapping->module_name)
            ->map(fn ($items) => $items->values())
            ->all();

        return Inertia::render('Apps/PostingRules/Index', [
            'rules' => $rules,
            'companies' => $this->getAccessibleCompanies(),
            'chartOfAccounts' => $this->scopeCompany(ChartOfAccount::query())
                ->select('id', 'company_id', 'code', 'name')
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
            'mappingByCompanyModule' => $mappings,
            'presetTemplates' => $this->presetTemplates(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $this->enforceCompanyAccess((int) $validated['company_id']);

        DB::transaction(function () use ($validated) {
            $rule = PostingRule::query()->create(Arr::except($validated, ['lines', 'coa_mappings']));

            foreach ($validated['lines'] as $line) {
                $rule->lines()->create($line);
            }

            foreach ($validated['coa_mappings'] as $mapping) {
                CoaMapping::query()->updateOrCreate(
                    [
                        'company_id' => (int) $validated['company_id'],
                        'module_name' => $validated['module_name'],
                        'mapping_key' => $mapping['mapping_key'],
                    ],
                    [
                        'account_id' => $mapping['account_id'],
                        'description' => $mapping['description'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('apps.preset-journals.index')->with('success', 'Preset jurnal berhasil dibuat.');
    }

    public function update(Request $request, PostingRule $integrationPostingRule): RedirectResponse
    {
        $this->enforceCompanyAccess((int) $integrationPostingRule->company_id);
        $validated = $this->validatePayload($request, $integrationPostingRule);
        $this->enforceCompanyAccess((int) $validated['company_id']);

        DB::transaction(function () use ($validated, $integrationPostingRule) {
            $integrationPostingRule->update(Arr::except($validated, ['lines', 'coa_mappings']));

            $integrationPostingRule->lines()->delete();
            foreach ($validated['lines'] as $line) {
                $integrationPostingRule->lines()->create($line);
            }

            CoaMapping::query()
                ->where('company_id', (int) $validated['company_id'])
                ->where('module_name', $validated['module_name'])
                ->whereIn('mapping_key', collect($validated['coa_mappings'])->pluck('mapping_key')->all())
                ->delete();

            foreach ($validated['coa_mappings'] as $mapping) {
                CoaMapping::query()->create([
                    'company_id' => (int) $validated['company_id'],
                    'module_name' => $validated['module_name'],
                    'mapping_key' => $mapping['mapping_key'],
                    'account_id' => $mapping['account_id'],
                    'description' => $mapping['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('apps.preset-journals.index')->with('success', 'Preset jurnal berhasil diperbarui.');
    }

    public function destroy(PostingRule $integrationPostingRule): RedirectResponse
    {
        $this->enforceCompanyAccess((int) $integrationPostingRule->company_id);
        $integrationPostingRule->delete();

        return redirect()->route('apps.preset-journals.index')->with('success', 'Preset jurnal berhasil dihapus.');
    }

    private function validatePayload(Request $request, ?PostingRule $postingRule = null): array
    {
        $companyId = (int) $request->input('company_id');
        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'module_name' => ['required', 'string', 'max:100'],
            'event_name' => ['required', 'string', 'max:100'],
            'transaction_type' => ['required', 'string', 'max:100'],
            'rule_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('posting_rules', 'rule_code')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->where('version', (int) $request->input('version', 1)))
                    ->ignore($postingRule?->id),
            ],
            'rule_name' => ['required', 'string', 'max:150'],
            'version' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.line_no' => ['required', 'integer', 'min:1'],
            'lines.*.line_side' => ['required', Rule::in(['debit', 'credit'])],
            'lines.*.account_source_type' => ['required', Rule::in(['fixed', 'mapping', 'payload', 'dynamic'])],
            'lines.*.fixed_account_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'lines.*.mapping_key' => ['nullable', 'string', 'max:150'],
            'lines.*.amount_source' => ['required', Rule::in(['payload_total', 'payload_tax', 'payload_net', 'formula'])],
            'lines.*.formula_json_text' => ['nullable', 'json'],
            'lines.*.formula_json' => ['nullable', 'array'],
            'lines.*.dimension_rule_json' => ['nullable', 'array'],
            'lines.*.description_template' => ['nullable', 'string', 'max:191'],
            'coa_mappings' => ['required', 'array', 'min:1'],
            'coa_mappings.*.mapping_key' => ['required', 'string', 'max:150'],
            'coa_mappings.*.account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'coa_mappings.*.description' => ['nullable', 'string', 'max:191'],
        ], [
            'lines.*.formula_json_text.json' => 'Formula JSON harus valid.',
            'lines.min' => 'Posting rule minimal memiliki 2 baris (debit dan credit).',
            'coa_mappings.min' => 'Minimal satu CoA mapping diperlukan.',
        ]);

        foreach ($validated['lines'] as &$line) {
            if (array_key_exists('formula_json_text', $line)) {
                $line['formula_json'] = filled($line['formula_json_text'])
                    ? json_decode($line['formula_json_text'], true)
                    : ($line['formula_json'] ?? null);
                unset($line['formula_json_text']);
            }
        }
        unset($line);

        return $validated;
    }

    private function presetTemplates(): array
    {
        return [
            'sales_invoice_posted' => [
                'label' => 'Sales Invoice Posted - Combined Sales + COGS',
                'data' => [
                    'module_name' => 'sales',
                    'event_name' => 'sales.invoice.posted',
                    'transaction_type' => 'sales.invoice.standard',
                    'rule_code' => 'SALES_INVOICE_POSTED_COMBINED',
                    'rule_name' => 'Sales Invoice Posted Combined Journal',
                    'description' => 'Combined journal untuk AR, diskon, revenue, PPN keluaran setelah diskon, ongkir, COGS, dan inventory.',
                    'lines' => [
                        ['line_no' => 1, 'line_side' => 'debit', 'mapping_key' => 'sales.invoice.debit.ar', 'amount_source' => 'formula', 'formula_json' => ['type' => 'sales_invoice_receivable_total'], 'description_template' => 'Sales invoice accounts receivable'],
                        ['line_no' => 2, 'line_side' => 'debit', 'mapping_key' => 'sales.invoice.debit.discount', 'amount_source' => 'formula', 'formula_json' => ['type' => 'path', 'path' => 'amounts.discount'], 'description_template' => 'Sales invoice discount'],
                        ['line_no' => 3, 'line_side' => 'credit', 'mapping_key' => 'sales.invoice.credit.revenue', 'amount_source' => 'formula', 'formula_json' => ['type' => 'path', 'path' => 'amounts.subtotal'], 'description_template' => 'Sales invoice revenue'],
                        ['line_no' => 4, 'line_side' => 'credit', 'mapping_key' => 'sales.invoice.credit.vat_output', 'amount_source' => 'formula', 'formula_json' => ['type' => 'sales_invoice_tax_after_discount'], 'description_template' => 'Sales invoice VAT output after discount'],
                        ['line_no' => 5, 'line_side' => 'credit', 'mapping_key' => 'sales.invoice.credit.freight_income', 'amount_source' => 'formula', 'formula_json' => ['type' => 'path', 'path' => 'amounts.shipping_fee'], 'description_template' => 'Sales invoice shipping income'],
                        ['line_no' => 6, 'line_side' => 'debit', 'mapping_key' => 'sales.invoice.debit.cogs', 'amount_source' => 'formula', 'formula_json' => ['type' => 'sales_invoice_cogs_total'], 'description_template' => 'Sales invoice COGS from dispatch cost'],
                        ['line_no' => 7, 'line_side' => 'credit', 'mapping_key' => 'sales.invoice.credit.inventory', 'amount_source' => 'formula', 'formula_json' => ['type' => 'sales_invoice_cogs_total'], 'description_template' => 'Sales invoice inventory reduction from dispatch cost'],
                    ],
                    'coa_mappings' => [
                        ['mapping_key' => 'sales.invoice.debit.ar', 'description' => 'Accounts receivable'],
                        ['mapping_key' => 'sales.invoice.debit.discount', 'description' => 'Sales discount / contra revenue'],
                        ['mapping_key' => 'sales.invoice.credit.revenue', 'description' => 'Sales revenue'],
                        ['mapping_key' => 'sales.invoice.credit.vat_output', 'description' => 'VAT output payable'],
                        ['mapping_key' => 'sales.invoice.credit.freight_income', 'description' => 'Shipping income'],
                        ['mapping_key' => 'sales.invoice.debit.cogs', 'description' => 'Cost of goods sold'],
                        ['mapping_key' => 'sales.invoice.credit.inventory', 'description' => 'Inventory reduction'],
                    ],
                ],
            ],
        ];
    }
}
