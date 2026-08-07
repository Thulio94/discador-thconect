<?php

namespace App\Services\Capacity;

use App\Exceptions\CapacityExceededException;
use App\Models\AsteriskNode;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\ChannelReservation;
use App\Models\Company;
use App\Models\SystemCapacitySetting;
use App\Models\TelephonyRoute;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelCapacityService
{
    private const ACTIVE_STATUSES = ['reserved', 'active'];

    public function __construct(private readonly AuditLogService $audit) {}

    public function reserve(Company $company, TelephonyRoute $route, ?Campaign $campaign = null, ?int $userId = null): ChannelReservation
    {
        $route->loadMissing('asteriskNode');
        $node = $route->asteriskNode;

        if (! $node || $node->status !== 'online') {
            throw new CapacityExceededException('node_unavailable', 'Nenhum no de telefonia esta disponivel para esta rota.');
        }

        try {
            return Cache::lock('capacity:reservation-lock', 10)->block(3, function () use ($company, $route, $campaign, $userId, $node): ChannelReservation {
                return DB::transaction(function () use ($company, $route, $campaign, $userId, $node): ChannelReservation {
                $settings = SystemCapacitySetting::query()->lockForUpdate()->firstOrFail();
                $company->loadMissing(['license', 'resourceLimit']);
                $this->purgeExpired();

                if ($settings->origination_blocked) {
                    $this->deny('global_manual_block', $company, 'Novas originacoes foram pausadas pelo SuperAdmin.');
                }

                $globalInUse = $this->inUse();
                if ($globalInUse >= $settings->max_channels) {
                    $this->deny('global_channels', $company, 'A capacidade global de telefonia foi atingida.');
                }

                $companyInUse = $this->inUse(companyId: $company->id);
                $companyLimit = $company->license?->max_concurrent_calls;
                if ($companyLimit !== null && $companyInUse >= $companyLimit) {
                    $this->deny('company_channels', $company, 'A capacidade simultanea da empresa foi atingida.');
                }

                if ($globalInUse >= (int) floor($settings->max_channels * ($settings->warning_percent / 100))) {
                    $weights = DB::table('company_resource_limits')
                        ->whereExists(fn ($query) => $query->selectRaw('1')->from('campaigns')->whereColumn('campaigns.company_id', 'company_resource_limits.company_id')->where('campaigns.status', 'active'))
                        ->pluck('dialer_weight', 'company_id');
                    $weights->put($company->id, $company->resourceLimit?->dialer_weight ?? 1);
                    $totalWeight = max(1, (int) $weights->sum());
                    $fairCeiling = max(1, (int) floor($settings->max_channels * ((int) $weights->get($company->id, 1) / $totalWeight)));
                    if ($companyInUse >= $fairCeiling) {
                        $this->deny('fair_share', $company, 'A empresa atingiu temporariamente sua parcela justa do cluster.');
                    }
                }

                $routeInUse = $this->inUse(routeId: $route->id);
                if ($route->max_concurrent_channels !== null && $routeInUse >= $route->max_concurrent_channels) {
                    $this->deny('route_channels', $company, 'A rota de telefonia atingiu sua capacidade contratada.');
                }

                $nodeInUse = $this->inUse(nodeId: $node->id);
                if ($nodeInUse >= $node->max_channels) {
                    $this->deny('node_channels', $company, 'O no de telefonia atingiu sua capacidade.');
                }

                $dailyLimit = $company->resourceLimit?->max_daily_attempts ?? 500000;
                $dailyAttempts = Call::query()->where('company_id', $company->id)->whereDate('started_at', today())->count();
                if ($dailyAttempts >= $dailyLimit) {
                    $this->deny('daily_attempts', $company, 'A protecao diaria de tentativas foi atingida.');
                }

                return ChannelReservation::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'campaign_id' => $campaign?->id,
                    'user_id' => $userId,
                    'telephony_route_id' => $route->id,
                    'asterisk_node_id' => $node->id,
                    'status' => 'reserved',
                    'expires_at' => now()->addMinutes(3),
                ]);
                });
            });
        } catch (CapacityExceededException $exception) {
            $this->audit->record('capacity.limit_reached', 'blocked', $exception->getMessage(), companyId: $company->id, metadata: ['limit' => $exception->limitKey]);
            throw $exception;
        }
    }

    public function activate(ChannelReservation|string|null $reservation, ?int $callId = null): void
    {
        $model = $this->find($reservation);
        $model?->update(['status' => 'active', 'call_id' => $callId, 'expires_at' => now()->addHours(8)]);
    }

    public function release(ChannelReservation|string|null $reservation, string $reason): void
    {
        $model = $this->find($reservation);
        if ($model && in_array($model->status, self::ACTIVE_STATUSES, true)) {
            $model->update(['status' => 'released', 'released_at' => now(), 'release_reason' => $reason]);
        }
    }

    public function reconcile(): int
    {
        return ChannelReservation::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhereHas('call', fn ($call) => $call->whereIn('status', ['ended', 'cancelled']));
            })
            ->update(['status' => 'released', 'released_at' => now(), 'release_reason' => 'reconciled']);
    }

    public function snapshot(): array
    {
        $settings = SystemCapacitySetting::current();
        $used = $this->inUse();
        $percent = $settings->max_channels > 0 ? round(($used / $settings->max_channels) * 100, 1) : 100;

        return compact('settings', 'used', 'percent') + [
            'state' => $settings->origination_blocked || $percent >= 100 ? 'blocked' : ($percent >= $settings->critical_percent ? 'critical' : ($percent >= $settings->warning_percent ? 'warning' : 'normal')),
            'companies' => Company::query()->with(['license', 'resourceLimit'])->withCount(['calls as active_calls' => fn ($q) => $q->whereIn('status', ['dialing', 'ringing', 'in_progress'])])->orderByDesc('active_calls')->get(),
            'routes' => TelephonyRoute::query()->with('asteriskNode')->withCount(['companies', 'reservations as channels_in_use' => fn ($q) => $q->whereIn('status', self::ACTIVE_STATUSES)->where('expires_at', '>', now())])->orderBy('name')->get(),
            'nodes' => AsteriskNode::query()->withCount(['routes', 'reservations as active_channels' => fn ($q) => $q->whereIn('status', self::ACTIVE_STATUSES)->where('expires_at', '>', now())])->orderBy('priority')->get(),
        ];
    }

    private function inUse(?int $companyId = null, ?int $routeId = null, ?int $nodeId = null): int
    {
        return ChannelReservation::query()->whereIn('status', self::ACTIVE_STATUSES)->where('expires_at', '>', now())
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($routeId, fn ($q) => $q->where('telephony_route_id', $routeId))
            ->when($nodeId, fn ($q) => $q->where('asterisk_node_id', $nodeId))
            ->count();
    }

    private function purgeExpired(): void
    {
        ChannelReservation::query()->whereIn('status', self::ACTIVE_STATUSES)->where('expires_at', '<=', now())
            ->update(['status' => 'released', 'released_at' => now(), 'release_reason' => 'expired']);
    }

    private function find(ChannelReservation|string|null $reservation): ?ChannelReservation
    {
        if ($reservation instanceof ChannelReservation) return $reservation;
        return $reservation ? ChannelReservation::query()->find($reservation) : null;
    }

    private function deny(string $key, Company $company, string $message): never
    {
        throw new CapacityExceededException($key, $message);
    }
}
