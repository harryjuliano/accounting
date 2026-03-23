<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
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

        Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'HO-0000'],
            [
                'name' => 'Head Office',
                'is_active' => true,
            ]
        );
    }
}
