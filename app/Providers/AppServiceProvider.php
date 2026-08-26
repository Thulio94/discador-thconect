<?php

namespace App\Providers;

use App\Contracts\Dialer\DialerGateway;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\CompanyTelephonyConfiguration;
use App\Models\OperationalParameter;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Observers\AgentStatusObserver;
use App\Observers\AuditableModelObserver;
use App\Observers\CallObserver;
use App\Services\Dialer\AsteriskDialerGateway;
use App\Support\GodMode\GodModeContext;
use App\Support\Tenant\PermissionMatrix;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DialerGateway::class, AsteriskDialerGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(3)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('password-confirm', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('imports', fn (Request $request): Limit => Limit::perMinute(8)
            ->by((string) $request->user()?->company_id.'|'.$request->ip()));
        RateLimiter::for('exports', fn (Request $request): Limit => Limit::perMinute(20)
            ->by((string) $request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('webhook-test', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->user()?->company_id.'|'.$request->ip()));
        RateLimiter::for('telephony-test', fn (Request $request): Limit => Limit::perMinute(3)
            ->by((string) $request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('bridge-events', fn (Request $request): Limit => Limit::perMinute(240)
            ->by($request->ip()));
        RateLimiter::for('agent-state', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('agent-softphone', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) $request->user()?->id.'|'.$request->ip()));

        Gate::before(fn (User $user): ?bool => app(GodModeContext::class)->isActiveFor($user) ? true : null);

        foreach (PermissionMatrix::keys() as $permissionKey) {
            Gate::define(
                'tenant.'.str_replace('.', '.', $permissionKey),
                fn (User $user): bool => $user->hasPermission($permissionKey),
            );
        }

        Gate::define('super-admin', fn (User $user): bool => (bool) $user->is_super_admin);

        Company::observe(AuditableModelObserver::class);
        AgentStatus::observe(AgentStatusObserver::class);
        Call::observe(CallObserver::class);
        CompanyLicense::observe(AuditableModelObserver::class);
        CompanyTelephonyConfiguration::observe(AuditableModelObserver::class);
        TelephonyRoute::observe(AuditableModelObserver::class);
        OperationalParameter::observe(AuditableModelObserver::class);
        User::observe(AuditableModelObserver::class);
    }
}
