<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('limits company-admin company options on integration client secret page', function () {
    $companyA = Company::create([
        'code' => 'CMP-A',
        'name' => 'Company A',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
        'is_active' => true,
    ]);

    $companyB = Company::create([
        'code' => 'CMP-B',
        'name' => 'Company B',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
        'is_active' => true,
    ]);

    Branch::create([
        'company_id' => $companyA->id,
        'code' => 'A-1',
        'name' => 'Branch A',
        'is_active' => true,
    ]);

    Branch::create([
        'company_id' => $companyB->id,
        'code' => 'B-1',
        'name' => 'Branch B',
        'is_active' => true,
    ]);

    $companyAdminRole = Role::firstOrCreate(['name' => 'company-admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'company_id' => $companyA->id,
    ]);
    $user->assignRole($companyAdminRole);

    $response = $this->actingAs($user)->get(route('apps.integration-client-secrets.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Apps/IntegrationClientSecrets/Index')
            ->where('companies.0.id', $companyA->id)
            ->where('companies.0.name', $companyA->name)
            ->missing('companies.1')
        );
});

it('prevents company-admin from creating credential for another company', function () {
    $companyA = Company::create([
        'code' => 'CMP-A2',
        'name' => 'Company A2',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
        'is_active' => true,
    ]);

    $companyB = Company::create([
        'code' => 'CMP-B2',
        'name' => 'Company B2',
        'base_currency_code' => 'IDR',
        'country_code' => 'ID',
        'is_active' => true,
    ]);

    Branch::create([
        'company_id' => $companyA->id,
        'code' => 'A-2',
        'name' => 'Branch A2',
        'is_active' => true,
    ]);

    $branchB = Branch::create([
        'company_id' => $companyB->id,
        'code' => 'B-2',
        'name' => 'Branch B2',
        'is_active' => true,
    ]);

    $companyAdminRole = Role::firstOrCreate(['name' => 'company-admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'company_id' => $companyA->id,
    ]);
    $user->assignRole($companyAdminRole);

    $response = $this
        ->actingAs($user)
        ->from(route('apps.integration-client-secrets.index'))
        ->post(route('apps.integration-client-secrets.store'), [
            'company_id' => $companyB->id,
            'branch_id' => $branchB->id,
            'source_module' => 'inventory',
            'client_name' => 'Cross Company',
            'client_secret' => 'secret-credential-123',
        ]);

    $response->assertForbidden();
});
