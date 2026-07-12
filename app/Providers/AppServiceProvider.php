<?php

namespace App\Providers;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\CompanyTelephonyConfiguration;
use App\Models\OperationalParameter;
use App\Models\User;
use App\Observers\AuditableModelObserver;
use App\Services\Dialer\SimulatedDialerGateway;
use App\Support\GodMode\GodModeContext;
use App\Support\Tenant\PermissionMatrix;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DialerGateway::class, SimulatedDialerGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => app(GodModeContext::class)->isActiveFor($user) ? true : null);

        foreach (PermissionMatrix::keys() as $permissionKey) {
            Gate::define(
                'tenant.'.str_replace('.', '.', $permissionKey),
                fn (User $user): bool => $user->hasPermission($permissionKey),
            );
        }

        Gate::define('super-admin', fn (User $user): bool => (bool) $user->is_super_admin);

        Company::observe(AuditableModelObserver::class);
        CompanyLicense::observe(AuditableModelObserver::class);
        CompanyTelephonyConfiguration::observe(AuditableModelObserver::class);
        OperationalParameter::observe(AuditableModelObserver::class);
        User::observe(AuditableModelObserver::class);
    }
}
