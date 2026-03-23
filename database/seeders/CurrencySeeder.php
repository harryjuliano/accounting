<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::updateOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesia Rupiah',
                'symbol' => 'Rp',
                'decimal_places' => 2,
                'is_active' => true,
            ]
        );
    }
}
