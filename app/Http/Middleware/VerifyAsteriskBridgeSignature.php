<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAsteriskBridgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.asterisk_bridge.webhook_secret');
        $signature = (string) $request->header('X-THCONECT-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (blank($secret) || blank($signature) || ! hash_equals($expected, $signature)) {
            abort(401);
        }

        return $next($request);
    }
}
