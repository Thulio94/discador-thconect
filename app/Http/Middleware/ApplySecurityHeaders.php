<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (config('app.env') === 'production') {
            $response->headers->set(
                'Content-Security-Policy',
                // Alpine evaluates the directives rendered by the server (x-show, x-on,
                // x-bind). Blocking this breaks all interactive controls and leaves modal
                // backdrops visible because their x-show expressions never run.
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-eval'; connect-src 'self' wss:"
            );
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
