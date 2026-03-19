<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(Request $request)
    {
        $search = $request->search;

        $currencies = Currency::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('code', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%')
                        ->orWhere('symbol', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('code')
            ->paginate(10, ['*'], 'currencies_page')
            ->withQueryString();

        $exchangeRates = ExchangeRate::query()
            ->with(['company:id,name', 'fromCurrency:code,name', 'toCurrency:code,name'])
            ->when($this->isCompanyAdmin(), fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('rate_type', 'like', '%' . $search . '%')
                        ->orWhere('source', 'like', '%' . $search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('fromCurrency', fn ($currencyQuery) => $currencyQuery
                            ->where('code', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('toCurrency', fn ($currencyQuery) => $currencyQuery
                            ->where('code', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%'));
                });
            })
            ->latest('rate_date')
            ->latest('id')
            ->paginate(10, ['*'], 'rates_page')
            ->withQueryString();

        return inertia('Apps/CurrenciesRates/Index', [
            'currencies' => $currencies,
            'exchangeRates' => $exchangeRates,
            'companies' => $this->getAccessibleCompanies(),
            'currencyOptions' => Currency::query()->select('code', 'name')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(CurrencyRequest $request)
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        Currency::create($validated);

        return back();
    }

    public function update(CurrencyRequest $request, Currency $currency)
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        $currency->update($validated);

        return back();
    }

    public function destroy(Currency $currency)
    {
        $currency->delete();

        return back();
    }
}
