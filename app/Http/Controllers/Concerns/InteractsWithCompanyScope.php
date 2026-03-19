<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithCompanyScope
{
    protected function isCompanyAdmin(): bool
    {
        $user = request()->user();

        return (bool) ($user && $user->hasRole('company-admin') && ! $user->isSuperAdmin());
    }

    protected function enforceCompanyAccess(?int $companyId): void
    {
        if (! $this->isCompanyAdmin()) {
            return;
        }

        abort_unless((int) request()->user()->company_id === (int) $companyId, 403, 'Anda tidak memiliki akses ke company ini.');
    }

    protected function scopeCompany(Builder $query, string $column = 'company_id'): Builder
    {
        if (! $this->isCompanyAdmin()) {
            return $query;
        }

        return $query->where($column, request()->user()->company_id);
    }

    protected function getAccessibleCompanies(): Collection
    {
        $query = Company::query()->select('id', 'name')->orderBy('name');

        if ($this->isCompanyAdmin()) {
            $query->where('id', request()->user()->company_id);
        }

        return $query->get();
    }
}
