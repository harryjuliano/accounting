<?php

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;

it('auto generates monthly accounting periods when creating a fiscal year', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-001',
        'name' => 'PT Test',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('apps.fiscal-periods.store'), [
            'company_id' => $company->id,
            'year_label' => '2026',
            'status' => 'open',
        ]);

    $response->assertSessionHasNoErrors();

    $fiscalYear = FiscalYear::where('company_id', $company->id)->where('year_label', '2026')->firstOrFail();
    expect($fiscalYear->start_date->toDateString())->toBe('2026-01-01');
    expect($fiscalYear->end_date->toDateString())->toBe('2026-12-31');

    $periods = AccountingPeriod::query()
        ->where('company_id', $company->id)
        ->where('fiscal_year_id', $fiscalYear->id)
        ->orderBy('period_no')
        ->get();

    expect($periods)->toHaveCount(12);
    expect($periods->first()->start_date->toDateString())->toBe('2026-01-01');
    expect($periods->first()->end_date->toDateString())->toBe('2026-01-31');
    expect($periods->last()->start_date->toDateString())->toBe('2026-12-01');
    expect($periods->last()->end_date->toDateString())->toBe('2026-12-31');
});
