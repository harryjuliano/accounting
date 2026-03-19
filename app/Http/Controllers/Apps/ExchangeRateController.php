<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateRequest;
use App\Models\ExchangeRate;

class ExchangeRateController extends Controller
{
    use InteractsWithCompanyScope;

    public function store(ExchangeRateRequest $request)
    {
        ExchangeRate::create($request->validated());

        return back();
    }

    public function update(ExchangeRateRequest $request, ExchangeRate $exchange_rate)
    {
        $this->enforceCompanyAccess((int) $exchange_rate->company_id);
        $exchange_rate->update($request->validated());

        return back();
    }

    public function destroy(ExchangeRate $exchange_rate)
    {
        $this->enforceCompanyAccess((int) $exchange_rate->company_id);
        $exchange_rate->delete();

        return back();
    }
}
