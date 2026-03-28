<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\IntegrationClientCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationClientSecretController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Apps/IntegrationClientSecrets/Index', [
            'companies' => Company::query()
                ->select('id', 'name')
                ->where('is_active', true)
                ->with(['branches' => fn ($query) => $query
                    ->select('id', 'company_id', 'name')
                    ->where('is_active', true)
                    ->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'credentials' => IntegrationClientCredential::query()
                ->select('id', 'client_key', 'source_module', 'client_name', 'company_id', 'branch_id', 'is_active', 'last_used_at', 'created_at')
                ->with([
                    'company:id,name',
                    'branch:id,name',
                ])
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'generatedCredential' => session('generatedCredential'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', (int) $request->input('company_id'))),
            ],
            'source_module' => ['required', 'string', 'max:50'],
            'client_name' => ['nullable', 'string', 'max:191'],
        ]);

        $branch = Branch::query()
            ->where('id', (int) $validated['branch_id'])
            ->where('company_id', (int) $validated['company_id'])
            ->first();

        if (! $branch) {
            return back()->withErrors([
                'branch_id' => 'Branch tidak ditemukan untuk company yang dipilih.',
            ]);
        }

        $clientKey = Str::upper($validated['source_module']) . '-' . Str::upper(Str::random(12));
        $clientSecret = Str::random(48);

        IntegrationClientCredential::query()->create([
            'client_key' => $clientKey,
            'client_secret_hash' => hash('sha256', $clientSecret),
            'source_module' => $validated['source_module'],
            'company_id' => (int) $validated['company_id'],
            'branch_id' => (int) $validated['branch_id'],
            'client_name' => $validated['client_name'] ?: null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('apps.integration-client-secrets.index')
            ->with('generatedCredential', [
                'client_key' => $clientKey,
                'client_secret' => $clientSecret,
            ]);
    }
}
