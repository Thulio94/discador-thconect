<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMetricsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.metrics.token');
        abort_if($expected === '' || ! hash_equals($expected, (string) $request->bearerToken()), 404);
        return $next($request);
    }
}
