<?php

namespace App\Services\SystemStatus;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemStatusService
{
    private const HEARTBEAT_TTL_SECONDS = 180;

    public function snapshot(): array
    {
        $components = [
            $this->probe('application', 'Aplicacao', 'Painel e API', fn (): null => null),
            $this->probe('database', 'Banco de dados', 'Persistencia de dados', fn (): mixed => DB::connection()->getPdo()),
            $this->probe('redis', 'Redis', 'Cache e transporte da fila', fn (): mixed => Redis::connection()->ping()),
            $this->probe('queue', 'Fila de processamento', 'Jobs assincronos', fn (): mixed => Queue::connection()->size()),
            $this->heartbeatComponent('scheduler', 'Agendador', 'Tarefas programadas'),
            $this->heartbeatComponent('queue-worker', 'Processador de fila', 'Execucao de jobs'),
            $this->probe('reverb', 'Tempo real', 'Eventos e atualizacoes', fn (): mixed => $this->probeReverb()),
        ];

        $worstState = collect($components)
            ->pluck('state')
            ->contains('outage')
            ? 'outage'
            : (collect($components)->pluck('state')->contains('degraded') ? 'degraded' : 'operational');

        return [
            'state' => $worstState,
            'title' => match ($worstState) {
                'operational' => 'Todos os servicos estao online',
                'degraded' => 'Atencao necessaria em alguns servicos',
                default => 'Falha detectada no sistema',
            },
            'checked_at' => now(),
            'components' => $components,
        ];
    }

    public function recordSchedulerHeartbeat(): void
    {
        $this->recordHeartbeat('scheduler');
    }

    public function recordQueueHeartbeat(): void
    {
        $this->recordHeartbeat('queue-worker');
    }

    private function probe(string $key, string $name, string $description, Closure $callback): array
    {
        $startedAt = hrtime(true);

        try {
            $callback();

            return $this->component($key, $name, $description, 'operational', 'Operacional', $startedAt);
        } catch (Throwable $exception) {
            return $this->component($key, $name, $description, 'outage', 'Indisponivel', $startedAt, $exception->getMessage());
        }
    }

    private function heartbeatComponent(string $key, string $name, string $description): array
    {
        $startedAt = hrtime(true);

        try {
            $heartbeat = Cache::get($this->heartbeatKey($key));

            if (! is_string($heartbeat)) {
                return $this->component($key, $name, $description, 'degraded', 'Aguardando sinal', $startedAt);
            }

            $heartbeatAt = Carbon::parse($heartbeat);

            return $this->component(
                $key,
                $name,
                $description,
                'operational',
                'Operacional',
                $startedAt,
                null,
                $heartbeatAt,
            );
        } catch (Throwable $exception) {
            return $this->component($key, $name, $description, 'outage', 'Indisponivel', $startedAt, $exception->getMessage());
        }
    }

    private function probeReverb(): mixed
    {
        $options = config('broadcasting.connections.reverb.options', []);
        $scheme = $options['scheme'] ?? 'http';
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 8080;

        $response = Http::connectTimeout(1)
            ->timeout(2)
            ->get("{$scheme}://{$host}:{$port}");

        if ($response->serverError()) {
            throw new \RuntimeException('O servidor de tempo real retornou erro.');
        }

        return $response;
    }

    private function recordHeartbeat(string $key): void
    {
        Cache::put(
            $this->heartbeatKey($key),
            now()->toIso8601String(),
            now()->addSeconds(self::HEARTBEAT_TTL_SECONDS),
        );
    }

    private function heartbeatKey(string $key): string
    {
        return "system-status:heartbeat:{$key}";
    }

    private function component(
        string $key,
        string $name,
        string $description,
        string $state,
        string $label,
        int $startedAt,
        ?string $error = null,
        ?CarbonInterface $heartbeatAt = null,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'state' => $state,
            'label' => $label,
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'checked_at' => now(),
            'heartbeat_at' => $heartbeatAt,
            'error' => $error,
        ];
    }
}
