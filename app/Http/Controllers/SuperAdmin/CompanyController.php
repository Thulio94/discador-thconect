<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('super-admin.companies.index', [
            'companies' => Company::query()->with('license')->latest()->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $company = Company::query()->create([
                'name' => $data['name'],
                'document' => $data['document'] ?? null,
                'status' => $data['status'],
            ]);

            $company->licenses()->create($this->licenseData($data));
        });

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa criada com sucesso.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($company, $data): void {
            $company->update([
                'name' => $data['name'],
                'document' => $data['document'] ?? null,
                'status' => $data['status'],
            ]);

            $company->licenses()->create($this->licenseData($data));
        });

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->update(['status' => 'inactive']);

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa inativada com sucesso.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'license_starts_at' => ['required', 'date'],
            'license_ends_at' => ['required', 'date', 'after_or_equal:license_starts_at'],
            'max_users' => ['required', 'integer', 'min:1', 'max:5000'],
            'max_concurrent_agents' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'max_calls_per_available_agent' => ['required', 'integer', 'min:1', 'max:20'],
            'max_concurrent_calls' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'power_enabled' => ['boolean'],
            'predictive_enabled' => ['boolean'],
            'preview_enabled' => ['boolean'],
            'amd_enabled' => ['boolean'],
        ]);
    }

    private function licenseData(array $data): array
    {
        return [
            'status' => 'active',
            'starts_at' => $data['license_starts_at'],
            'ends_at' => $data['license_ends_at'],
            'max_users' => $data['max_users'],
            'max_concurrent_agents' => $data['max_concurrent_agents'] ?? null,
            'max_calls_per_available_agent' => $data['max_calls_per_available_agent'],
            'max_concurrent_calls' => $data['max_concurrent_calls'] ?? null,
            'power_enabled' => (bool) ($data['power_enabled'] ?? false),
            'predictive_enabled' => (bool) ($data['predictive_enabled'] ?? false),
            'preview_enabled' => (bool) ($data['preview_enabled'] ?? false),
            'amd_enabled' => (bool) ($data['amd_enabled'] ?? false),
        ];
    }
}
