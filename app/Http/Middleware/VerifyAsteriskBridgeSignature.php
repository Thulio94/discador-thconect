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
        $timestamp = (int) $request->header('X-THCONECT-Timestamp');
        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        if (blank($secret) || blank($signature) || $timestamp <= 0 || abs(now()->timestamp - $timestamp) > 300 || ! hash_equals($expected, $signature)) {
            abort(401);
        }

        return $next($request);
    }
}
