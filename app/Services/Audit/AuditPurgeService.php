<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class AuditPurgeService
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function estimate(array $data): int
    {
        return $this->query($data)->count();
    }

    public function purge(array $data, User $actor): int
    {
        return DB::transaction(function () use ($data, $actor): int {
            $query = $this->query($data);
            $count = $query->count();

            if ($count === 0) {
                return 0;
            }

            $query->delete();

            $this->audit->record(
                event: 'audit.purged',
                action: 'purged',
                description: "{$count} registros de auditoria removidos.",
                companyId: $data['scope'] === 'company' ? (int) $data['company_id'] : null,
                actor: $actor,
                metadata: [
                    'deleted_count' => $count,
                    'date_from' => $data['date_from'],
                    'date_to' => $data['date_to'],
                    'scope' => $data['scope'],
                    'reason' => $data['reason'],
                ],
            );

            return $count;
        });
    }

    private function query(array $data): Builder
    {
        return AuditLog::query()
            ->when(
                $data['scope'] === 'global',
                fn (Builder $query) => $query->whereNull('company_id'),
                fn (Builder $query) => $query->where('company_id', (int) $data['company_id']),
            )
            ->where('created_at', '>=', CarbonImmutable::parse($data['date_from'])->startOfDay())
            ->where('created_at', '<=', CarbonImmutable::parse($data['date_to'])->endOfDay());
    }
}
