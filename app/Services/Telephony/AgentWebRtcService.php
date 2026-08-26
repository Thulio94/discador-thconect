<?php

namespace App\Services\Telephony;

use App\Models\TelephonyRoute;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class AgentWebRtcService
{
    public function __construct(private readonly AsteriskBridgeClient $bridge) {}

    /** @return array{endpoint: string, password: string, extension: string, node_url: string} */
    public function synchronize(User $user): array
    {
        if ($user->status !== 'active' || $user->user_type !== 'agent' || ! $user->company_id || ! preg_match('/^\d{4,5}$/', (string) $user->extension)) {
            throw new RuntimeException('O agente nao possui um ramal ativo para WebRTC.');
        }

        $route = $this->routeFor($user);
        $secret = $user->sip_secret;

        if (blank($secret)) {
            $secret = Str::random(48);
            $user->forceFill(['sip_secret' => $secret])->saveQuietly();
        }

        $endpoint = $this->endpointName($user);
        $this->bridge->synchronizeAgent($route->asteriskNode, [
            'company_id' => $user->company_id,
            'extension' => (string) $user->extension,
            'password' => $secret,
            'codecs' => 'opus,ulaw,alaw',
        ], $user->id);

        return [
            'endpoint' => $endpoint,
            'password' => $secret,
            'extension' => (string) $user->extension,
            'node_url' => (string) $route->asteriskNode?->bridge_url,
        ];
    }

    public function remove(User $user): void
    {
        if (! $user->company_id) {
            return;
        }

        $route = $this->routeFor($user, false);
        if ($route?->asteriskNode) {
            $this->bridge->removeAgent($route->asteriskNode, $user->id, $user->company_id);
        }

        if ($user->sip_secret !== null) {
            $user->forceFill(['sip_secret' => null])->saveQuietly();
        }
    }

    public function endpointName(User $user): string
    {
        return 't'.$user->company_id.'-e'.$user->extension;
    }

    private function routeFor(User $user, bool $required = true): ?TelephonyRoute
    {
        $routes = $user->company?->telephonyRoutes()
            ->with('asteriskNode');

        if ($required) {
            $routes?->where('enabled', true);
        }

        $route = $routes
            ->orderBy('priority')
            ->orderBy('telephony_routes.id')
            ->first();

        if ($required && (! $route || ! $route->asteriskNode)) {
            throw new RuntimeException('A empresa nao possui uma rota Asterisk ativa.');
        }

        return $route;
    }
}
