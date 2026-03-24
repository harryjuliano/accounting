<?php

use App\Models\User;
use App\Models\Company;

it('renders opening balance page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('apps.opening-balances.index'), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();
});

it('downloads opening balance template csv', function () {
    $company = Company::create([
        'code' => 'CMP-TPL',
        'name' => 'PT Template',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $response = $this->actingAs($user)->get(route('apps.opening-balances.import-template'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('journal_no,entry_date,posting_date,reference_no,description,currency_code,exchange_rate,status,branch_code,account_code,line_description,debit,credit');
});
