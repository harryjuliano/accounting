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

        $groups = collect([
            ['code' => 'AST', 'name' => 'Assets', 'type' => 'asset'],
            ['code' => 'LIA', 'name' => 'Liabilities', 'type' => 'liability'],
            ['code' => 'EQT', 'name' => 'Equity', 'type' => 'equity'],
            ['code' => 'REV', 'name' => 'Revenue', 'type' => 'revenue'],
            ['code' => 'EXP', 'name' => 'Expenses', 'type' => 'expense'],
        ])->mapWithKeys(function (array $group) use ($company) {
            $model = AccountGroup::firstOrCreate(
                ['company_id' => $company->id, 'code' => $group['code']],
                ['name' => $group['name'], 'type' => $group['type']]
            );

            return [$group['code'] => $model];
        });

        $accounts = [
            [
                'code' => '1000',
                'name' => 'Kas dan Setara Kas',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'financial_statement_group' => 'neraca',
                'cashflow_group' => 'operating',
                'account_group_id' => $groups['AST']->id,
            ],
            [
                'code' => '1100',
                'name' => 'Piutang Usaha',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'financial_statement_group' => 'neraca',
                'cashflow_group' => 'operating',
                'account_group_id' => $groups['AST']->id,
            ],
            [
                'code' => '2000',
                'name' => 'Utang Usaha',
                'account_type' => 'liability',
                'normal_balance' => 'credit',
                'financial_statement_group' => 'neraca',
                'cashflow_group' => 'operating',
                'account_group_id' => $groups['LIA']->id,
            ],
            [
                'code' => '3000',
                'name' => 'Modal Disetor',
                'account_type' => 'equity',
                'normal_balance' => 'credit',
                'financial_statement_group' => 'neraca',
                'cashflow_group' => 'financing',
                'account_group_id' => $groups['EQT']->id,
            ],
            [
                'code' => '4000',
                'name' => 'Pendapatan Penjualan',
                'account_type' => 'revenue',
                'normal_balance' => 'credit',
                'financial_statement_group' => 'laba_rugi',
                'cashflow_group' => 'operating',
                'account_group_id' => $groups['REV']->id,
            ],
            [
                'code' => '5000',
                'name' => 'Beban Operasional',
                'account_type' => 'expense',
                'normal_balance' => 'debit',
                'financial_statement_group' => 'laba_rugi',
                'cashflow_group' => 'operating',
                'account_group_id' => $groups['EXP']->id,
            ],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                [
                    'account_group_id' => $account['account_group_id'],
                    'parent_id' => null,
                    'name' => $account['name'],
                    'alias_name' => null,
                    'level' => 1,
                    'account_type' => $account['account_type'],
                    'normal_balance' => $account['normal_balance'],
                    'financial_statement_group' => $account['financial_statement_group'],
                    'cashflow_group' => $account['cashflow_group'],
                    'allow_manual_posting' => true,
                    'allow_reconciliation' => false,
                    'requires_dimension' => false,
                    'is_control_account' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}
