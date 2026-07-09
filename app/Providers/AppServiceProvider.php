<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\User;
use App\Observers\AuditableModelObserver;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (PermissionMatrix::keys() as $permissionKey) {
            Gate::define(
                'tenant.'.str_replace('.', '.', $permissionKey),
                fn (User $user): bool => $user->hasPermission($permissionKey),
            );
        }

        Gate::define('super-admin', fn (User $user): bool => (bool) $user->is_super_admin);

        Company::observe(AuditableModelObserver::class);
        CompanyLicense::observe(AuditableModelObserver::class);
        User::observe(AuditableModelObserver::class);
    }
}
