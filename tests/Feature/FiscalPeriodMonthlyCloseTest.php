<?php

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;

it('can soft close and reopen monthly accounting period', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-002',
        'name' => 'PT Bulanan',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'year_label' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    $period = AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 1,
        'period_name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => 'open',
    ]);

    $this
        ->actingAs($user)
        ->post(route('apps.fiscal-periods.accounting-periods.toggle-close', [$fiscalYear->id, $period->id]))
        ->assertSessionHasNoErrors();

    $period->refresh();
    expect($period->status)->toBe('soft_closed');
    expect($period->closed_at)->not->toBeNull();
    expect($period->closed_by)->toBe($user->id);

    $this
        ->actingAs($user)
        ->post(route('apps.fiscal-periods.accounting-periods.toggle-close', [$fiscalYear->id, $period->id]))
        ->assertSessionHasNoErrors();

    $period->refresh();
    expect($period->status)->toBe('open');
    expect($period->closed_at)->toBeNull();
    expect($period->closed_by)->toBeNull();
});

it('can hard close fiscal year and force all months to hard_closed', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'code' => 'CMP-004',
        'name' => 'PT Tahunan',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
    ]);

    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'year_label' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 1,
        'period_name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_no' => 2,
        'period_name' => 'February 2026',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'status' => 'soft_closed',
    ]);

    $this
        ->actingAs($user)
        ->post(route('apps.fiscal-periods.hard-close', $fiscalYear->id))
        ->assertSessionHasNoErrors();

    $fiscalYear->refresh();
    expect($fiscalYear->status)->toBe('closed');

    $statuses = AccountingPeriod::query()
        ->where('fiscal_year_id', $fiscalYear->id)
        ->pluck('status')
        ->unique()
        ->values()
        ->all();

    expect($statuses)->toBe(['hard_closed']);
});
