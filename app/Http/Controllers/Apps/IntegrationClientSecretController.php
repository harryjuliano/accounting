<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Concerns\InteractsWithCompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\IntegrationClientCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationClientSecretController extends Controller
{
    use InteractsWithCompanyScope;

    public function index(): Response
    {
        $integrationTableReady = Schema::hasTable('integration_client_credentials');

        $credentials = $integrationTableReady
            ? $this->scopeCompany(IntegrationClientCredential::query())
                ->select('id', 'client_key', 'source_module', 'client_name', 'company_id', 'branch_id', 'is_active', 'last_used_at', 'created_at')
                ->with([
                    'company:id,name',
                    'branch:id,name',
                ])
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
            : [
                'data' => [],
                'current_page' => 1,
                'per_page' => 10,
                'last_page' => 1,
                'links' => [],
            ];

        return Inertia::render('Apps/IntegrationClientSecrets/Index', [
            'companies' => $this->scopeCompany(Company::query(), 'id')
                ->select('id', 'name')
                ->where('is_active', true)
                ->with(['branches' => fn ($query) => $query
                    ->select('id', 'company_id', 'name')
                    ->where('is_active', true)
                    ->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'credentials' => $credentials,
            'generatedCredential' => session('generatedCredential'),
            'integrationTableReady' => $integrationTableReady,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('integration_client_credentials')) {
            return back()->withErrors([
                'integration' => 'Tabel integration_client_credentials belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $validated = $this->validateCredential($request);
        $this->enforceCompanyAccess((int) $validated['company_id']);

        $clientKey = Str::upper($validated['source_module']) . '-' . Str::upper(Str::random(12));
        $clientSecret = $validated['client_secret'] ?: Str::random(48);

        IntegrationClientCredential::query()->create([
            'client_key' => $clientKey,
            'client_secret_hash' => hash('sha256', $clientSecret),
            'source_module' => Str::lower($validated['source_module']),
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

    public function update(Request $request, IntegrationClientCredential $integrationClientSecret): RedirectResponse
    {
        $validated = $this->validateCredential($request);
        $this->enforceCompanyAccess((int) $integrationClientSecret->company_id);
        $this->enforceCompanyAccess((int) $validated['company_id']);

        $updates = [
            'source_module' => Str::lower($validated['source_module']),
            'company_id' => (int) $validated['company_id'],
            'branch_id' => (int) $validated['branch_id'],
            'client_name' => $validated['client_name'] ?: null,
        ];

        $newSecret = null;
        if (! empty($validated['client_secret'])) {
            $newSecret = $validated['client_secret'];
            $updates['client_secret_hash'] = hash('sha256', $newSecret);
        }

        $integrationClientSecret->update($updates);

        return redirect()
            ->route('apps.integration-client-secrets.index')
            ->with('generatedCredential', [
                'client_key' => $integrationClientSecret->client_key,
                'client_secret' => $newSecret,
            ]);
    }

    public function destroy(IntegrationClientCredential $integrationClientSecret): RedirectResponse
    {
        $this->enforceCompanyAccess((int) $integrationClientSecret->company_id);
        $integrationClientSecret->delete();

        return redirect()
            ->route('apps.integration-client-secrets.index')
            ->with('success', 'Client credential berhasil dihapus.');
    }

    public function toggleStatus(IntegrationClientCredential $integrationClientSecret): RedirectResponse
    {
        $this->enforceCompanyAccess((int) $integrationClientSecret->company_id);
        $integrationClientSecret->update([
            'is_active' => ! $integrationClientSecret->is_active,
        ]);

        return redirect()
            ->route('apps.integration-client-secrets.index')
            ->with('success', 'Status client credential berhasil diperbarui.');
    }

    private function validateCredential(Request $request): array
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
            'client_secret' => ['nullable', 'string', 'min:12', 'max:100'],
        ]);

        $branch = Branch::query()
            ->where('id', (int) $validated['branch_id'])
            ->where('company_id', (int) $validated['company_id'])
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch tidak ditemukan untuk company yang dipilih.',
            ]);
        }

        return $validated;
    }
}
