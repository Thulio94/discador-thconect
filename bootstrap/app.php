<?php

use App\Http\Middleware\ApplyGodModeCompanyContext;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\BlockGodModeTelephonyActions;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\VerifyAsteriskBridgeSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ApplySecurityHeaders::class);

        $middleware->alias([
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.user' => EnsureTenantUser::class,
            'god-mode' => ApplyGodModeCompanyContext::class,
            'god-mode.telephony-safe' => BlockGodModeTelephonyActions::class,
            'super.admin' => EnsureUserIsSuperAdmin::class,
            'security.headers' => ApplySecurityHeaders::class,
            'asterisk.bridge' => VerifyAsteriskBridgeSignature::class,
        ]);

        $middleware->validateCsrfTokens(except: ['bridge/events']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
