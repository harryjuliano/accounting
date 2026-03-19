<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Dimension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request)
    {
        $baseQuery = ChartOfAccount::query()
            ->with(['company:id,name', 'accountGroup:id,name', 'parent:id,code,name', 'dimensions:id,company_id,name'])
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('account_type', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            });

        $masterChartOfAccounts = (clone $baseQuery)
            ->latest()
            ->paginate(10, ['*'], 'master_page')
            ->withQueryString();

        $transactionChartOfAccounts = (clone $baseQuery)
            ->where('level', 4)
            ->latest()
            ->paginate(10, ['*'], 'transaction_page')
            ->withQueryString();

        $companies = $this->getAccessibleCompanies();
        $accountGroups = AccountGroup::query()->select('id', 'company_id', 'name')
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->orderBy('name')->get();
        $parentAccounts = ChartOfAccount::query()
            ->select('id', 'company_id', 'account_group_id', 'code', 'name', 'level', 'account_type', 'financial_statement_group')
            ->where('level', 3)
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->orderBy('code')
            ->get();
        $dimensions = Dimension::query()->select('id', 'company_id', 'name')->where('is_active', true)
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->orderBy('name')->get();

        return inertia('Apps/ChartOfAccounts/Index', [
            'masterChartOfAccounts' => $masterChartOfAccounts,
            'transactionChartOfAccounts' => $transactionChartOfAccounts,
            'companies' => $companies,
            'accountGroups' => $accountGroups,
            'parentAccounts' => $parentAccounts,
            'dimensions' => $dimensions,
        ]);
    }

    public function store(ChartOfAccountRequest $request)
    {
        $payload = $request->validated();
        $this->applyTransactionDefaults($payload);
        $dimensionIds = $payload['dimension_ids'] ?? [];
        unset($payload['dimension_ids']);

        $this->validateDimensionCompany($payload['company_id'], $dimensionIds);

        DB::transaction(function () use ($payload, $dimensionIds) {
            $chartOfAccount = ChartOfAccount::create($payload);
            $chartOfAccount->dimensions()->sync($dimensionIds);
        });

        return back();
    }

    public function update(ChartOfAccountRequest $request, ChartOfAccount $chart_of_account)
    {
        $this->enforceCompanyAccess((int) $chart_of_account->company_id);

        $payload = $request->validated();
        $this->applyTransactionDefaults($payload);
        $dimensionIds = $payload['dimension_ids'] ?? [];
        unset($payload['dimension_ids']);

        $this->validateDimensionCompany($payload['company_id'], $dimensionIds);

        DB::transaction(function () use ($chart_of_account, $payload, $dimensionIds) {
            $chart_of_account->update($payload);
            $chart_of_account->dimensions()->sync($dimensionIds);
        });

        return back();
    }

    public function destroy(ChartOfAccount $chart_of_account)
    {
        $this->enforceCompanyAccess((int) $chart_of_account->company_id);
        $chart_of_account->delete();

        return back();
    }

    public function importDefaultTemplate(Request $request)
    {
        $payload = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $companyId = (int) $payload['company_id'];
        $this->enforceCompanyAccess($companyId);

        $parentAccounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->get(['id', 'code', 'account_group_id', 'account_type', 'financial_statement_group'])
            ->keyBy('code');

        $template = $this->defaultTransactionTemplate();
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($companyId, $parentAccounts, $template, &$imported, &$skipped) {
            foreach ($template as $item) {
                $parent = $parentAccounts->get($item['parent_code']);

                if (! $parent) {
                    $skipped++;

                    continue;
                }

                $accountType = $parent->account_type;

                ChartOfAccount::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'code' => $item['code'],
                    ],
                    [
                        'account_group_id' => $parent->account_group_id,
                        'parent_id' => $parent->id,
                        'name' => $item['name'],
                        'alias_name' => $item['alias_name'],
                        'level' => 4,
                        'account_type' => $accountType,
                        'normal_balance' => in_array($accountType, ['asset', 'assets', 'expense', 'cogs'], true) ? 'debit' : 'credit',
                        'financial_statement_group' => $parent->financial_statement_group,
                        'cashflow_group' => null,
                        'allow_manual_posting' => true,
                        'allow_reconciliation' => false,
                        'requires_dimension' => false,
                        'is_control_account' => false,
                        'is_active' => true,
                    ]
                );

                $imported++;
            }
        });

        return back()->with('success', "Berhasil import {$imported} template COA transaksi. {$skipped} dilewati karena parent level 3 tidak ditemukan.");
    }

    private function validateDimensionCompany(int $companyId, array $dimensionIds): void
    {
        if (empty($dimensionIds)) {
            return;
        }

        $invalidExists = Dimension::query()
            ->whereIn('id', $dimensionIds)
            ->where('company_id', '!=', $companyId)
            ->exists();

        if ($invalidExists) {
            throw ValidationException::withMessages([
                'dimension_ids' => 'Semua dimension harus berasal dari company yang sama dengan akun COA.',
            ]);
        }
    }

    private function applyTransactionDefaults(array &$payload): void
    {
        if (($payload['form_type'] ?? null) !== 'transaction') {
            unset($payload['form_type']);

            return;
        }

        $parentAccount = ChartOfAccount::query()
            ->select('id', 'account_group_id', 'account_type', 'financial_statement_group')
            ->find($payload['parent_id']);

        $payload['level'] = 4;
        $payload['account_group_id'] = $parentAccount?->account_group_id;
        $payload['account_type'] = $parentAccount?->account_type;
        $payload['financial_statement_group'] = $parentAccount?->financial_statement_group;

        unset($payload['form_type']);
    }

    private function defaultTransactionTemplate(): array
    {
        return [
            ['code' => '1110-010', 'name' => 'Petty Cash', 'alias_name' => 'Kas Kecil', 'parent_code' => '1110'],
            ['code' => '1120-010', 'name' => 'Bank BCA', 'alias_name' => 'Bank BCA', 'parent_code' => '1120'],
            ['code' => '1120-020', 'name' => 'Bank Mandiri', 'alias_name' => 'Bank Mandiri', 'parent_code' => '1120'],
            ['code' => '1120-030', 'name' => 'Bank BNI', 'alias_name' => 'Bank BNI', 'parent_code' => '1120'],
            ['code' => '1120-040', 'name' => 'Bank HSBC', 'alias_name' => 'Bank HSBC', 'parent_code' => '1120'],
            ['code' => '1130-010', 'name' => 'Accounts Receivable - Trade', 'alias_name' => 'Piutang Usaha', 'parent_code' => '1130'],
            ['code' => '1130-900', 'name' => 'Allowance for Doubtful Accounts', 'alias_name' => 'Cadangan Penghapusan Piutang', 'parent_code' => '1130'],
            ['code' => '1150-010', 'name' => 'Inventory FG', 'alias_name' => 'Persedian Barang Jadi', 'parent_code' => '1150'],
            ['code' => '1150-020', 'name' => 'Inventory RW', 'alias_name' => 'Persedian Bahan Baku', 'parent_code' => '1150'],
            ['code' => '1150-900', 'name' => 'Inventory GIT', 'alias_name' => 'Persediaan Barang Dalam Perjalanan', 'parent_code' => '1150'],
            ['code' => '1160-010', 'name' => 'Prepaid Rental', 'alias_name' => 'Uang Muka Sewa', 'parent_code' => '1160'],
            ['code' => '1160-020', 'name' => 'Prepaid Purchase', 'alias_name' => 'Uang Muka Pembelian', 'parent_code' => '1160'],
            ['code' => '1160-900', 'name' => 'Prepaid Others', 'alias_name' => 'Uang Muka Lainnya', 'parent_code' => '1160'],
            ['code' => '1170-010', 'name' => 'Prepaid Tax - Art 22', 'alias_name' => 'Uang Muka Pajak - PPh 22', 'parent_code' => '1170'],
            ['code' => '1170-020', 'name' => 'Prepaid Tax - Art 23', 'alias_name' => 'Uang Muka Pajak - PPh 23', 'parent_code' => '1170'],
            ['code' => '1170-030', 'name' => 'Prepaid Tax - Art 25', 'alias_name' => 'Uang Muka Pajak - PPh 25', 'parent_code' => '1170'],
            ['code' => '1170-040', 'name' => 'Prepaid Tax - Vat In', 'alias_name' => 'Uang Muka Pajak - PPN Masukan', 'parent_code' => '1170'],
            ['code' => '1180-010', 'name' => 'Intercompany Receivables', 'alias_name' => 'Piutang kepada Inter Company', 'parent_code' => '1180'],
            ['code' => '1210-010', 'name' => 'Land', 'alias_name' => 'Tanah', 'parent_code' => '1210'],
            ['code' => '1210-020', 'name' => 'Building', 'alias_name' => 'Bangunan', 'parent_code' => '1210'],
            ['code' => '1210-030', 'name' => 'Machinary', 'alias_name' => 'Mesin', 'parent_code' => '1210'],
            ['code' => '1210-040', 'name' => 'Motor Vehicle', 'alias_name' => 'Kendaraan', 'parent_code' => '1210'],
            ['code' => '1210-050', 'name' => 'Equipment', 'alias_name' => 'Peralatan', 'parent_code' => '1210'],
            ['code' => '1210-060', 'name' => 'Furniture', 'alias_name' => 'Furniture', 'parent_code' => '1210'],
            ['code' => '1210-900', 'name' => 'Others Asset', 'alias_name' => 'Asset Lainnya', 'parent_code' => '1210'],
            ['code' => '1310-020', 'name' => 'Acc Dep Land', 'alias_name' => 'Acc Dep Tanah', 'parent_code' => '1310'],
            ['code' => '1310-030', 'name' => 'Acc Dep Building', 'alias_name' => 'Acc Dep Bangunan', 'parent_code' => '1310'],
            ['code' => '1310-040', 'name' => 'Acc Dep Machinary', 'alias_name' => 'Acc Dep Mesin', 'parent_code' => '1310'],
            ['code' => '1310-050', 'name' => 'Acc Dep Motor Vehicle', 'alias_name' => 'Acc Dep Kendaraan', 'parent_code' => '1310'],
            ['code' => '1310-060', 'name' => 'Acc Dep Equipment', 'alias_name' => 'Acc Dep Peralatan', 'parent_code' => '1310'],
            ['code' => '1310-900', 'name' => 'Acc Dep Furniture', 'alias_name' => 'Acc Dep Furniture', 'parent_code' => '1310'],
            ['code' => '1320-010', 'name' => 'Intangible Assets', 'alias_name' => 'Aktiva Tidak Berwujud', 'parent_code' => '1320'],
            ['code' => '1330-010', 'name' => 'Investment', 'alias_name' => 'Investasi', 'parent_code' => '1330'],
            ['code' => '2110-010', 'name' => 'Accounts Payable', 'alias_name' => 'Hutang Usaha', 'parent_code' => '2110'],
            ['code' => '2120-010', 'name' => 'Accrued Salary', 'alias_name' => 'Accrued Gaji', 'parent_code' => '2120'],
            ['code' => '2120-020', 'name' => 'Accrued Bonus', 'alias_name' => 'Accrued Bonus', 'parent_code' => '2120'],
            ['code' => '2120-030', 'name' => 'Accrued Others', 'alias_name' => 'Accrued Others', 'parent_code' => '2120'],
            ['code' => '2130-010', 'name' => 'Tax Payable - Art 21', 'alias_name' => 'Hutang Pajak - Pph Ps 21', 'parent_code' => '2130'],
            ['code' => '2130-020', 'name' => 'Tax Payable - Art 23', 'alias_name' => 'Hutang Pajak - Pph Ps 23', 'parent_code' => '2130'],
            ['code' => '2130-030', 'name' => 'Tax Payable - Art 25', 'alias_name' => 'Hutang Pajak - PPh Ps 25', 'parent_code' => '2130'],
            ['code' => '2130-040', 'name' => 'Tax Payable - Art 29', 'alias_name' => 'Hutang Pajak - PPh Ps 29', 'parent_code' => '2130'],
            ['code' => '2130-050', 'name' => 'Tax Payable - PPh Final', 'alias_name' => 'Hutang Pajak - Pph Final', 'parent_code' => '2130'],
            ['code' => '2130-060', 'name' => 'Tax Payable - Vat Out', 'alias_name' => 'Hutang Pajak - PPN Keluaran', 'parent_code' => '2130'],
            ['code' => '2150-010', 'name' => 'Customer Deposit', 'alias_name' => 'Uang Muka Penjualan', 'parent_code' => '2150'],
            ['code' => '2160-010', 'name' => 'Intercompany Payables', 'alias_name' => 'Hutang Kepada Inter Company', 'parent_code' => '2160'],
            ['code' => '2210-010', 'name' => 'Bank Loan', 'alias_name' => 'Hutang Bank', 'parent_code' => '2210'],
            ['code' => '2210-020', 'name' => 'Lease Payable', 'alias_name' => 'Hutang Leasing', 'parent_code' => '2210'],
            ['code' => '2220-010', 'name' => 'Deferred Tax Liabilities', 'alias_name' => 'Deferred Tax Liabilities', 'parent_code' => '2220'],
            ['code' => '3110-010', 'name' => 'Share Capital', 'alias_name' => 'Modal Saham', 'parent_code' => '3110'],
            ['code' => '3110-020', 'name' => 'Additional Paid-in Capital', 'alias_name' => 'Tambahan Modal Saham', 'parent_code' => '3110'],
            ['code' => '3110-030', 'name' => 'Dividends', 'alias_name' => 'Deviden', 'parent_code' => '3110'],
            ['code' => '3110-040', 'name' => 'Non-controlling Interest', 'alias_name' => 'Non-controlling Interest', 'parent_code' => '3110'],
            ['code' => '3110-050', 'name' => 'Retained Earnings', 'alias_name' => 'Laba Ditahan', 'parent_code' => '3110'],
            ['code' => '3110-900', 'name' => 'Current Year Profit/Loss', 'alias_name' => 'Laba Tahun Berjalan', 'parent_code' => '3110'],
            ['code' => '4110-010', 'name' => 'Service Revenue', 'alias_name' => 'Pendapatan Jasa', 'parent_code' => '4110'],
            ['code' => '4120-010', 'name' => 'Sales', 'alias_name' => 'Penjualan Barang', 'parent_code' => '4120'],
            ['code' => '4120-020', 'name' => 'Sales Discount', 'alias_name' => 'Diskon Penjualan', 'parent_code' => '4120'],
            ['code' => '4120-030', 'name' => 'Sales Return', 'alias_name' => 'Retur Penjualan', 'parent_code' => '4120'],
            ['code' => '5110-010', 'name' => 'Cost Of Services', 'alias_name' => 'Service Costs', 'parent_code' => '5110'],
            ['code' => '5120-020', 'name' => 'Cost of Good Sold', 'alias_name' => 'Cost of Good Sold', 'parent_code' => '5120'],
            ['code' => '5130-010', 'name' => 'Direct Labour Cost', 'alias_name' => 'Manufacturing Costs', 'parent_code' => '5130'],
            ['code' => '5130-020', 'name' => 'Direct Material Cost', 'alias_name' => 'Manufacturing Costs', 'parent_code' => '5130'],
            ['code' => '5130-030', 'name' => 'Packing Material Cost', 'alias_name' => 'Manufacturing Costs', 'parent_code' => '5130'],
            ['code' => '5130-040', 'name' => 'Utility Cost', 'alias_name' => 'Manufacturing Costs', 'parent_code' => '5130'],
            ['code' => '6110-010', 'name' => 'Factory Overhead', 'alias_name' => 'Factory Overhead', 'parent_code' => '6110'],
            ['code' => '7110-010', 'name' => 'Salary', 'alias_name' => 'Salary', 'parent_code' => '7110'],
            ['code' => '7110-020', 'name' => 'Bonus&THR', 'alias_name' => 'Bonus&THR', 'parent_code' => '7110'],
            ['code' => '7110-030', 'name' => 'Overtime', 'alias_name' => 'Overtime', 'parent_code' => '7110'],
            ['code' => '7110-040', 'name' => 'Food&transport', 'alias_name' => 'Food&transport', 'parent_code' => '7110'],
            ['code' => '7110-050', 'name' => 'Medical', 'alias_name' => 'Medical', 'parent_code' => '7110'],
            ['code' => '7110-060', 'name' => 'Tunjangan PPh 21', 'alias_name' => 'Tunjangan PPh 21', 'parent_code' => '7110'],
            ['code' => '7120-010', 'name' => 'Office Expenses', 'alias_name' => 'Office Expenses', 'parent_code' => '7120'],
            ['code' => '7120-020', 'name' => 'Electricity', 'alias_name' => 'Electricity', 'parent_code' => '7120'],
            ['code' => '7120-030', 'name' => 'Telephone,Fax,e-mail', 'alias_name' => 'Telephone,Fax,e-mail', 'parent_code' => '7120'],
            ['code' => '7120-040', 'name' => 'Printing, Stationary', 'alias_name' => 'Printing, Stationary', 'parent_code' => '7120'],
            ['code' => '7120-050', 'name' => 'Pos & Meterai', 'alias_name' => 'Pos & Meterai', 'parent_code' => '7120'],
            ['code' => '7120-060', 'name' => 'Traveling', 'alias_name' => 'Traveling', 'parent_code' => '7120'],
            ['code' => '7120-070', 'name' => 'Office Rental', 'alias_name' => 'Office Rental', 'parent_code' => '7120'],
            ['code' => '7120-080', 'name' => 'Office Facilities', 'alias_name' => 'Office Facilities', 'parent_code' => '7120'],
            ['code' => '7130-010', 'name' => 'Selling & Distribution', 'alias_name' => 'Selling & Distribution', 'parent_code' => '7130'],
            ['code' => '7130-020', 'name' => 'Freight', 'alias_name' => 'Freight', 'parent_code' => '7130'],
            ['code' => '7130-030', 'name' => 'Sample', 'alias_name' => 'Sample', 'parent_code' => '7130'],
            ['code' => '7130-040', 'name' => 'Advertising', 'alias_name' => 'Advertising', 'parent_code' => '7130'],
            ['code' => '7130-050', 'name' => 'Sales Commision', 'alias_name' => 'Komisi penjualan', 'parent_code' => '7130'],
            ['code' => '7140-010', 'name' => 'Maintenance Expenses', 'alias_name' => 'Maintenance Expenses', 'parent_code' => '7140'],
            ['code' => '7140-020', 'name' => 'Fuel,Oil,parking', 'alias_name' => 'Fuel,Oil,parking', 'parent_code' => '7140'],
            ['code' => '7140-030', 'name' => 'MR Motor Vehicle', 'alias_name' => 'MR Motor Vehicle', 'parent_code' => '7140'],
            ['code' => '7140-040', 'name' => 'MR Office Equipment', 'alias_name' => 'MR Office Equipment', 'parent_code' => '7140'],
            ['code' => '7140-050', 'name' => 'MR Building', 'alias_name' => 'MR Building', 'parent_code' => '7140'],
            ['code' => '7140-060', 'name' => 'MR Others', 'alias_name' => 'MR Others', 'parent_code' => '7140'],
            ['code' => '7150-010', 'name' => 'Depreciation Expenses', 'alias_name' => 'Biaya Penyusutan', 'parent_code' => '7150'],
            ['code' => '7160-010', 'name' => 'General Expenses', 'alias_name' => 'General Expenses', 'parent_code' => '7160'],
            ['code' => '7160-020', 'name' => 'Insurance', 'alias_name' => 'Insurance', 'parent_code' => '7160'],
            ['code' => '7160-030', 'name' => 'Donation', 'alias_name' => 'Donation', 'parent_code' => '7160'],
            ['code' => '7160-040', 'name' => 'Entertainment', 'alias_name' => 'Entertainment', 'parent_code' => '7160'],
            ['code' => '7160-050', 'name' => 'Bank Charges', 'alias_name' => 'Bank Charges', 'parent_code' => '7160'],
            ['code' => '7160-060', 'name' => 'Others Expenses', 'alias_name' => 'Others Expenses', 'parent_code' => '7160'],
            ['code' => '7160-070', 'name' => 'Licences', 'alias_name' => 'Licences', 'parent_code' => '7160'],
            ['code' => '7160-080', 'name' => 'Management Fee', 'alias_name' => 'Biaya Manajemen', 'parent_code' => '7160'],
            ['code' => '8110-010', 'name' => 'Others Income', 'alias_name' => 'Others Income', 'parent_code' => '8110'],
            ['code' => '8110-020', 'name' => 'Interest Income Deposit', 'alias_name' => 'Interest Income Deposit', 'parent_code' => '8110'],
            ['code' => '8510-010', 'name' => 'Pajak Jasa Giro', 'alias_name' => 'Pajak Jasa Giro', 'parent_code' => '8510'],
            ['code' => '8510-020', 'name' => 'Interest Expenses', 'alias_name' => 'Interest Expenses', 'parent_code' => '8510'],
            ['code' => '8510-030', 'name' => 'PL Forex', 'alias_name' => 'PL Forex', 'parent_code' => '8510'],
            ['code' => '8510-040', 'name' => 'PL of Disposal FA', 'alias_name' => 'PL of Disposal FA', 'parent_code' => '8510'],
            ['code' => '8510-050', 'name' => 'Tax Pinalty', 'alias_name' => 'Tax Pinalty', 'parent_code' => '8510'],
            ['code' => '8510-060', 'name' => 'Rounding', 'alias_name' => 'Rounding', 'parent_code' => '8510'],
            ['code' => '9110-010', 'name' => 'Corporate Tax', 'alias_name' => 'Pajak Penghasilan', 'parent_code' => '9110'],
        ];
    }
}
