<?php

namespace App\Http\Middleware;

use App\Support\GodMode\GodModeContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyGodModeCompanyContext
{
    public function __construct(private readonly GodModeContext $godMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->godMode->isActiveFor($user)) {
            return $next($request);
        }

        $company = $this->godMode->company();

        if ($company === null) {
            $this->godMode->leave();

            return $next($request);
        }

        // The authenticated identity stays the Super Admin. Only this request receives the tenant scope.
        $tenantContextUser = clone $user;
        $tenantContextUser->setAttribute('company_id', $company->id);
        $tenantContextUser->setRelation('company', $company);

        $request->setUserResolver(fn () => $tenantContextUser);
        Auth::setUser($tenantContextUser);

        return $next($request);
    }
}
