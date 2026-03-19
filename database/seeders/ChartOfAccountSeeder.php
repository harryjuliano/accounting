<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Company',
                'legal_name' => 'Main Company Indonesia',
                'tax_id' => null,
                'base_currency_code' => 'IDR',
                'country_code' => 'ID',
                'timezone' => 'Asia/Jakarta',
                'fiscal_year_start_month' => 1,
                'is_active' => true,
            ]
        );

        $groupDefinitions = [
            'assets' => ['code' => 'AST', 'name' => 'Assets', 'type' => 'asset'],
            'liabilities' => ['code' => 'LIA', 'name' => 'Liabilities', 'type' => 'liability'],
            'equity' => ['code' => 'EQT', 'name' => 'Equity', 'type' => 'equity'],
            'revenue' => ['code' => 'REV', 'name' => 'Revenue', 'type' => 'revenue'],
            'expense' => ['code' => 'EXP', 'name' => 'Expense', 'type' => 'expense'],
        ];

        $groups = collect($groupDefinitions)->mapWithKeys(function (array $group, string $key) use ($company) {
            $model = AccountGroup::updateOrCreate(
                ['company_id' => $company->id, 'code' => $group['code']],
                ['name' => $group['name'], 'type' => $group['type']]
            );

            return [$key => $model];
        });

        $accounts = [
            ['code' => '1000', 'name' => 'Assets', 'alias_name' => 'Aset', 'level' => 1, 'parent_code' => null, 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2000', 'name' => 'Liabilities', 'alias_name' => 'Liabilitas', 'level' => 1, 'parent_code' => null, 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '3000', 'name' => 'Equity', 'alias_name' => 'Ekuitas', 'level' => 1, 'parent_code' => null, 'account_group' => 'equity', 'account_type' => 'equity', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '4000', 'name' => 'Revenue', 'alias_name' => 'Pendapatan', 'level' => 1, 'parent_code' => null, 'account_group' => 'revenue', 'account_type' => 'revenue', 'financial_statement_group' => 'income_statement'],
            ['code' => '5000', 'name' => 'Expense', 'alias_name' => 'Biaya', 'level' => 1, 'parent_code' => null, 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],

            ['code' => '1100', 'name' => 'Current Assets', 'alias_name' => 'Aset Lancar', 'level' => 2, 'parent_code' => '1000', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1200', 'name' => 'Non-Current Assets', 'alias_name' => 'Aset Tidak Lancar', 'level' => 2, 'parent_code' => '1000', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2100', 'name' => 'Current Liabilities', 'alias_name' => 'Liabilitas Jangka Pendek', 'level' => 2, 'parent_code' => '2000', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2200', 'name' => 'Non-Current Liabilities', 'alias_name' => 'Liabilitas Jangka Panjang', 'level' => 2, 'parent_code' => '2000', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '3100', 'name' => 'Equity', 'alias_name' => 'Ekuitas', 'level' => 2, 'parent_code' => '3000', 'account_group' => 'equity', 'account_type' => 'equity', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '4100', 'name' => 'Revenue', 'alias_name' => 'Pendapatan', 'level' => 2, 'parent_code' => '4000', 'account_group' => 'revenue', 'account_type' => 'revenue', 'financial_statement_group' => 'income_statement'],
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'alias_name' => 'Harga Pokok Penjualan', 'level' => 2, 'parent_code' => '5000', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '6100', 'name' => 'Factory Overhead', 'alias_name' => 'Biaya Overhead', 'level' => 2, 'parent_code' => '5000', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7100', 'name' => 'Operating Expenses', 'alias_name' => 'Beban Operasional', 'level' => 2, 'parent_code' => '5000', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '8100', 'name' => 'Other Income/Expenses', 'alias_name' => 'Pendapatan/Biaya Lain-Lain', 'level' => 2, 'parent_code' => '5000', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '9100', 'name' => 'Corporate Tax', 'alias_name' => 'Pajak Penghasilan', 'level' => 2, 'parent_code' => '5000', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],

            ['code' => '1110', 'name' => 'Cash on Hand', 'alias_name' => 'Kas', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1120', 'name' => 'Bank Account', 'alias_name' => 'Bank', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1130', 'name' => 'Accounts Receivable', 'alias_name' => 'Piutang Usaha', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1140', 'name' => 'Allowance for Doubtful Accounts', 'alias_name' => 'Cadangan Kerugian Piutang', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1150', 'name' => 'Inventory', 'alias_name' => 'Persediaan Barang Dagang', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1160', 'name' => 'Prepaid', 'alias_name' => 'Biaya Dibayar di Muka', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1170', 'name' => 'Prepaid Tax', 'alias_name' => 'Uang Muka Pajak', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1180', 'name' => 'Intercompany Receivables', 'alias_name' => 'Piutang kepada Inter Company', 'level' => 3, 'parent_code' => '1100', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1210', 'name' => 'Fixed Asset', 'alias_name' => 'Aktiva Tetap', 'level' => 3, 'parent_code' => '1200', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1310', 'name' => 'Accumulated Depreciation', 'alias_name' => 'Akumulasi Penyusutan Aktiva', 'level' => 3, 'parent_code' => '1200', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1320', 'name' => 'Intangible Assets', 'alias_name' => 'Aktiva Tidak Berwujud', 'level' => 3, 'parent_code' => '1200', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '1330', 'name' => 'Investment', 'alias_name' => 'Investasi', 'level' => 3, 'parent_code' => '1200', 'account_group' => 'assets', 'account_type' => 'assets', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2110', 'name' => 'Accounts Payable', 'alias_name' => 'Hutang Usaha', 'level' => 3, 'parent_code' => '2100', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2120', 'name' => 'Accrued Expenses', 'alias_name' => 'Hutang Biaya', 'level' => 3, 'parent_code' => '2100', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2130', 'name' => 'Taxes Payable', 'alias_name' => 'Hutang Pajak', 'level' => 3, 'parent_code' => '2100', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2150', 'name' => 'Unearned Revenue', 'alias_name' => 'Pendapatan di terima di muka', 'level' => 3, 'parent_code' => '2100', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2160', 'name' => 'Intercompany Payables', 'alias_name' => 'Hutang Kepada Inter Company', 'level' => 3, 'parent_code' => '2100', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2210', 'name' => 'Long-term Loans', 'alias_name' => 'Hutang Jangka Panjang', 'level' => 3, 'parent_code' => '2200', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '2220', 'name' => 'Deferred Tax Liabilities', 'alias_name' => 'Deferred Tax Liabilities', 'level' => 3, 'parent_code' => '2200', 'account_group' => 'liabilities', 'account_type' => 'liabilities', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '3110', 'name' => 'Equity', 'alias_name' => 'Modal', 'level' => 3, 'parent_code' => '3100', 'account_group' => 'equity', 'account_type' => 'equity', 'financial_statement_group' => 'balance_sheet'],
            ['code' => '4110', 'name' => 'Service Revenue', 'alias_name' => 'Pendapatan Jasa', 'level' => 3, 'parent_code' => '4100', 'account_group' => 'revenue', 'account_type' => 'revenue', 'financial_statement_group' => 'income_statement'],
            ['code' => '4120', 'name' => 'Trading Revenue', 'alias_name' => 'Penjualan Barang', 'level' => 3, 'parent_code' => '4100', 'account_group' => 'revenue', 'account_type' => 'revenue', 'financial_statement_group' => 'income_statement'],
            ['code' => '5110', 'name' => 'Cost Of Services', 'alias_name' => 'Service Costs', 'level' => 3, 'parent_code' => '5100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '5120', 'name' => 'Cost of Good Sold', 'alias_name' => 'Cost of Good Sold', 'level' => 3, 'parent_code' => '5100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '5130', 'name' => 'Manufacturing Costs', 'alias_name' => 'Manufacturing Costs', 'level' => 3, 'parent_code' => '5100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '6110', 'name' => 'Factory Overhead', 'alias_name' => 'Factory Overhead', 'level' => 3, 'parent_code' => '6100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7110', 'name' => 'Staff On Cost', 'alias_name' => 'Biaya Gaji dan Upah', 'level' => 3, 'parent_code' => '7100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7120', 'name' => 'Office Expenses', 'alias_name' => 'Biaya Kantor', 'level' => 3, 'parent_code' => '7100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7130', 'name' => 'Distribution Expenses', 'alias_name' => 'Biaya Pemasaran', 'level' => 3, 'parent_code' => '7100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7140', 'name' => 'Repair Maintenance Expenses', 'alias_name' => 'Biaya Perbaikan', 'level' => 3, 'parent_code' => '7100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7150', 'name' => 'Depreciation Expenses', 'alias_name' => 'Biaya Penyusutan', 'level' => 3, 'parent_code' => '7100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '7160', 'name' => 'Other Operational Expenses', 'alias_name' => 'Biaya Oprasional Lainnya', 'level' => 3, 'parent_code' => '7100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '8110', 'name' => 'Other Income', 'alias_name' => 'Pendapatan Lain-Lain', 'level' => 3, 'parent_code' => '8100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '8510', 'name' => 'Other Expenses', 'alias_name' => 'Biaya Lain-Lain', 'level' => 3, 'parent_code' => '8100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
            ['code' => '9110', 'name' => 'Corporate Tax', 'alias_name' => 'Pajak Penghasilan', 'level' => 3, 'parent_code' => '9100', 'account_group' => 'expense', 'account_type' => 'expense', 'financial_statement_group' => 'income_statement'],
        ];

        $coaByCode = [];

        foreach ($accounts as $account) {
            $parentId = $account['parent_code'] ? ($coaByCode[$account['parent_code']]->id ?? null) : null;
            $normalBalance = in_array($account['account_type'], ['assets', 'expense'], true) ? 'debit' : 'credit';

            $coa = ChartOfAccount::updateOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                [
                    'account_group_id' => $groups[$account['account_group']]->id,
                    'parent_id' => $parentId,
                    'name' => $account['name'],
                    'alias_name' => $account['alias_name'],
                    'level' => $account['level'],
                    'account_type' => $account['account_type'],
                    'normal_balance' => $normalBalance,
                    'financial_statement_group' => $account['financial_statement_group'],
                    'cashflow_group' => null,
                    'allow_manual_posting' => false,
                    'allow_reconciliation' => false,
                    'requires_dimension' => false,
                    'is_control_account' => false,
                    'is_active' => true,
                ]
            );

            $coaByCode[$account['code']] = $coa;
        }
    }
}
