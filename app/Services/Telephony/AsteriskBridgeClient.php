<?php

namespace App\Services\Telephony;

use App\Models\AsteriskNode;
use App\Models\TelephonyRoute;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsteriskBridgeClient
{
    /** @return array<string, mixed> */
    public function testRoute(TelephonyRoute $route): array
    {
        return $this->request($route->asteriskNode)
            ->post('/v1/routes/test', $this->routePayload($route, 1))
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function synchronize(TelephonyRoute $route, int $companyId): array
    {
        return $this->request($route->asteriskNode)
            ->put('/v1/routes/'.$companyId, $this->routePayload($route, $companyId))
            ->throw()
            ->json();
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function originate(array $payload, ?AsteriskNode $node = null): array
    {
        return $this->request($node)->post('/v1/calls/originate', $payload)->throw()->json();
    }

    /** @param array<string, mixed> $payload */
    public function hangup(array $payload, ?AsteriskNode $node = null): void
    {
        $this->request($node)->post('/v1/calls/hangup', $payload)->throw();
    }

    /** @param array<string, mixed> $payload */
    public function supervise(array $payload): void
    {
        $this->request()->post('/v1/calls/supervise', $payload)->throw();
    }

    private function request(?AsteriskNode $node = null): PendingRequest
    {
        $token = (string) ($node?->bridge_token ?: config('services.asterisk_bridge.token'));

        if (blank($token)) {
            throw new RuntimeException('A ponte Asterisk ainda nao possui o token interno configurado.');
        }

        return Http::baseUrl(rtrim((string) ($node?->bridge_url ?: config('services.asterisk_bridge.url')), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('services.asterisk_bridge.timeout', 8));
    }

    /** @return array<string, mixed> */
    private function routePayload(TelephonyRoute $route, int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'route_id' => $route->id,
            'host' => config('services.asterisk_bridge.manager_host'),
            'port' => config('services.asterisk_bridge.manager_port'),
            'username' => config('services.asterisk_bridge.manager_username'),
            'secret' => config('services.asterisk_bridge.manager_secret'),
            'caller_id' => $route->caller_id,
            'channel_template' => 'PJSIP/{phone}@thconect-route-'.$route->id,
            'supervisor_channel_template' => 'PJSIP/{extension}',
            'context' => $route->dialplan_context,
            'extension' => $route->dialplan_extension,
            'sip_host' => $route->host,
            'from_domain' => $route->from_domain ?: $route->host,
            'sip_username' => $route->sip_username,
            'sip_password' => $route->sip_password,
            'authentication_mode' => $route->authentication_mode,
            'transport' => $route->transport,
            'codecs' => $route->codecs,
        ];
    }
}
