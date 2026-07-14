<?php

namespace App\Services\Telephony;

use App\Models\CompanyTelephonyConfiguration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsteriskBridgeClient
{
    /** @return array<string, mixed> */
    public function test(CompanyTelephonyConfiguration $configuration): array
    {
        return $this->request()
            ->post('/v1/routes/test', $this->routePayload($configuration))
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function synchronize(CompanyTelephonyConfiguration $configuration): array
    {
        return $this->request()
            ->put('/v1/routes/'.$configuration->company_id, $this->routePayload($configuration))
            ->throw()
            ->json();
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function originate(array $payload): array
    {
        return $this->request()->post('/v1/calls/originate', $payload)->throw()->json();
    }

    /** @param array<string, mixed> $payload */
    public function hangup(array $payload): void
    {
        $this->request()->post('/v1/calls/hangup', $payload)->throw();
    }

    private function request(): PendingRequest
    {
        $token = (string) config('services.asterisk_bridge.token');

        if (blank($token)) {
            throw new RuntimeException('A ponte Asterisk ainda nao possui o token interno configurado.');
        }

        return Http::baseUrl(rtrim((string) config('services.asterisk_bridge.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('services.asterisk_bridge.timeout', 8));
    }

    /** @return array<string, mixed> */
    private function routePayload(CompanyTelephonyConfiguration $configuration): array
    {
        return [
            'company_id' => $configuration->company_id,
            'host' => $configuration->ami_host,
            'port' => $configuration->ami_port,
            'username' => $configuration->ami_username,
            'secret' => $configuration->ami_secret,
            'caller_id' => $configuration->caller_id,
            'channel_template' => $configuration->outbound_channel_template,
            'context' => $configuration->dialplan_context,
            'extension' => $configuration->dialplan_extension,
        ];
    }
}
