<?php

use App\Http\Middleware\ApplyGodModeCompanyContext;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\BlockGodModeTelephonyActions;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\ReleaseAgentConsolePresence;
use App\Http\Middleware\VerifyAsteriskBridgeSignature;
use App\Http\Middleware\VerifyMetricsToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O app so e' acessivel externamente pelo proxy reverso do Easypanel.
        // Confiar no protocolo encaminhado evita assets HTTP em uma pagina HTTPS.
        // Only accept forwarding headers from loopback and private reverse-proxy networks.
        // Trusting every source lets a direct client forge X-Forwarded-* headers.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
        )));

        $middleware->trustProxies(at: $trustedProxies);

        $middleware->append(ApplySecurityHeaders::class);

        $middleware->alias([
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.user' => EnsureTenantUser::class,
            'agent.console.presence' => ReleaseAgentConsolePresence::class,
            'god-mode' => ApplyGodModeCompanyContext::class,
            'god-mode.telephony-safe' => BlockGodModeTelephonyActions::class,
            'super.admin' => EnsureUserIsSuperAdmin::class,
            'security.headers' => ApplySecurityHeaders::class,
            'asterisk.bridge' => VerifyAsteriskBridgeSignature::class,
            'metrics.token' => VerifyMetricsToken::class,
        ]);

        $middleware->validateCsrfTokens(except: ['bridge/events']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
