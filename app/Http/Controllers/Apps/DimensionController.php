<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\DimensionRequest;
use App\Models\Company;
use App\Models\Dimension;
use Illuminate\Http\Request;

class DimensionController extends Controller
{
    public function index(Request $request)
    {
        $dimensions = Dimension::query()
            ->with('company:id,name')
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('code', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')
                        ->orWhere('type', 'like', '%' . $request->search . '%')
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $companies = Company::query()->select('id', 'name')->orderBy('name')->get();

        return inertia('Apps/Dimensions/Index', [
            'dimensions' => $dimensions,
            'companies' => $companies,
        ]);
    }

    public function store(DimensionRequest $request)
    {
        Dimension::create($request->validated());

        return back();
    }

    public function update(DimensionRequest $request, Dimension $dimension)
    {
        $dimension->update($request->validated());

        return back();
    }

    public function destroy(Dimension $dimension)
    {
        $dimension->delete();

        return back();
    }
}
