<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\TelephonyRoute;
use App\Services\Licensing\LicenseUsageService;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request, LicenseUsageService $usage): View
    {
        $showInactive = $request->boolean('show_inactive');
        $companies = Company::query()
            ->with(['license', 'resourceLimit', 'telephonyRoutes'])
            ->when(! $showInactive, fn ($query) => $query->where('status', '!=', 'inactive'))
            ->withCount([
                'calls as active_calls_count' => fn ($query) => $query->where('status', 'in_progress'),
            ])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $usageByCompany = $usage->forCompanies($companies->getCollection());

        $companies->getCollection()->transform(function (Company $company) use ($usageByCompany): Company {
            $company->setAttribute('license_usage', $usageByCompany[$company->id]);

            return $company;
        });

        return view('super-admin.companies.index', [
            'companies' => $companies,
            'licenseOverview' => $usage->overview(),
            'showInactive' => $showInactive,
            'inactiveCompaniesCount' => Company::query()->where('status', 'inactive')->count(),
            'telephonyRoutes' => TelephonyRoute::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AsteriskBridgeClient $bridge): RedirectResponse
    {
        $data = $this->validated($request);

        $company = DB::transaction(function () use ($data): Company {
            $company = Company::query()->create([
                'name' => $data['name'],
                'document' => $data['document'] ?? null,
                'status' => $data['status'],
                'recording_retention_days' => $data['recording_retention_days'] ?? 90,
            ]);

            $company->licenses()->create($this->licenseData($data));
            $company->resourceLimit()->create($this->resourceLimitData($data));
            $company->telephonyRoutes()->sync($data['telephony_route_ids'] ?? []);

            return $company;
        });

        $this->synchronizeAssignedRoute($company, $bridge);

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa criada com sucesso.');
    }

    public function update(Request $request, Company $company, AsteriskBridgeClient $bridge): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($company, $data): void {
            $company->update([
                'name' => $data['name'],
                'document' => $data['document'] ?? null,
                'status' => $data['status'],
                'recording_retention_days' => $data['recording_retention_days'] ?? null,
            ]);

            $company->telephonyRoutes()->sync($data['telephony_route_ids'] ?? []);
            $company->resourceLimit()->updateOrCreate([], $this->resourceLimitData($data));

            $license = $company->license ?? $company->licenses()->latest('id')->first();

            if ($license) {
                $license->update($this->licenseData($data, $license));
            } else {
                $company->licenses()->create($this->licenseData($data));
            }
        });

        $this->synchronizeAssignedRoute($company, $bridge);

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

    public function block(Company $company): RedirectResponse
    {
        abort_if($company->status === 'inactive', 422, 'Empresas inativas nao podem ser bloqueadas.');

        $company->update(['status' => 'blocked']);

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa bloqueada. Os usuarios nao poderao mais acessar a operacao.');
    }

    public function unblock(Company $company): RedirectResponse
    {
        $company->update(['status' => 'active']);

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa desbloqueada com sucesso.');
    }

    public function activate(Company $company): RedirectResponse
    {
        $company->update(['status' => 'active']);

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Empresa reativada com sucesso.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'blocked', 'inactive'])],
            'recording_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'telephony_route_ids' => ['nullable', 'array'],
            'telephony_route_ids.*' => [Rule::exists('telephony_routes', 'id')],
            'license_starts_at' => ['nullable', 'date'],
            'license_ends_at' => ['nullable', 'date', 'after_or_equal:license_starts_at'],
            'billing_due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'max_calls_per_available_agent' => ['required', 'integer', 'min:1', 'max:10'],
            'max_concurrent_agents' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_concurrent_calls' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'power_enabled' => ['boolean'],
            'predictive_enabled' => ['boolean'],
            'preview_enabled' => ['boolean'],
            'amd_enabled' => ['boolean'],
            'max_registered_users' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'max_active_contacts' => ['sometimes', 'integer', 'min:1', 'max:10000000'],
            'max_import_rows' => ['sometimes', 'integer', 'min:1000', 'max:1000000'],
            'max_concurrent_imports' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_active_campaigns' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'max_active_webhooks' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'max_concurrent_exports' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_csv_rows' => ['sometimes', 'integer', 'min:25', 'max:5000000'],
            'max_xlsx_rows' => ['sometimes', 'integer', 'min:25', 'max:1000000'],
            'max_pdf_rows' => ['sometimes', 'integer', 'min:25', 'max:100000'],
            'max_report_range_days' => ['sometimes', 'integer', 'min:1', 'max:366'],
            'max_daily_attempts' => ['sometimes', 'integer', 'min:1', 'max:10000000'],
            'dialer_weight' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'hot_history_months' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ]);
    }

    private function resourceLimitData(array $data): array
    {
        return collect($data)->only([
            'max_registered_users', 'max_active_contacts', 'max_import_rows', 'max_concurrent_imports',
            'max_active_campaigns', 'max_active_webhooks', 'max_concurrent_exports', 'max_csv_rows',
            'max_xlsx_rows', 'max_pdf_rows', 'max_report_range_days', 'max_daily_attempts',
            'dialer_weight', 'hot_history_months',
        ])->all();
    }

    private function licenseData(array $data, ?CompanyLicense $existingLicense = null): array
    {
        return [
            'status' => 'active',
            // The contract period remains in the database for operational continuity.
            // It is managed internally; monthly billing is configured only by due day.
            'starts_at' => $data['license_starts_at'] ?? $existingLicense?->starts_at?->toDateString() ?? today()->toDateString(),
            'ends_at' => $data['license_ends_at'] ?? $existingLicense?->ends_at?->toDateString() ?? today()->addYears(10)->toDateString(),
            'billing_due_day' => $data['billing_due_day'],
            'max_calls_per_available_agent' => $data['max_calls_per_available_agent'],
            'max_concurrent_agents' => $data['max_concurrent_agents'] ?? null,
            'max_concurrent_calls' => $data['max_concurrent_calls'] ?? null,
            'power_enabled' => (bool) ($data['power_enabled'] ?? false),
            'predictive_enabled' => (bool) ($data['predictive_enabled'] ?? false),
            'preview_enabled' => (bool) ($data['preview_enabled'] ?? false),
            'amd_enabled' => (bool) ($data['amd_enabled'] ?? false),
        ];
    }

    private function synchronizeAssignedRoute(Company $company, AsteriskBridgeClient $bridge): void
    {
        $company->fresh(['telephonyRoutes'])?->telephonyRoutes
            ->filter(fn (TelephonyRoute $route): bool => $route->isReady() && $route->connection_status === 'online')
            ->each(function (TelephonyRoute $route) use ($bridge, $company): void {
                try {
                    $bridge->synchronize($route, $company->id);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
    }
}
