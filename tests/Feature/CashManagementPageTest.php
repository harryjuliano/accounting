<?php

use App\Models\Company;
use App\Models\User;

it('renders cash management dashboard from the accounting menu route', function () {
    $company = Company::create([
        'code' => 'CMP-CASH-MGMT',
        'name' => 'PT Cash Management',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('apps.cash-management.index'), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response
        ->assertOk()
        ->assertSee('CASH MANAGEMENT')
        ->assertSee('Transaksi → Integration Event → Auto Journal');
});
