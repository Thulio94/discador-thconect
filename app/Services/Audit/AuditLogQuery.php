<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AuditLogQuery
{
    public function paginate(array $filters, ?int $tenantCompanyId): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with(['company', 'actor'])
            ->where('event', 'not like', 'god_mode.%')
            ->when(
                $tenantCompanyId !== null,
                fn ($builder) => $builder->where('company_id', $tenantCompanyId),
            );

        if ($tenantCompanyId === null) {
            $query
                ->when(
                    filter_var($filters['global_only'] ?? false, FILTER_VALIDATE_BOOL),
                    fn ($builder) => $builder->whereNull('company_id'),
                )
                ->when(
                    ! filter_var($filters['global_only'] ?? false, FILTER_VALIDATE_BOOL)
                        && filled($filters['company_id'] ?? null),
                    fn ($builder) => $builder->where('company_id', (int) $filters['company_id']),
                );
        }

        $query
            ->when(
                filled($filters['date_from'] ?? null),
                fn ($builder) => $builder->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()),
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn ($builder) => $builder->where('created_at', '<=', CarbonImmutable::parse($filters['date_to'])->endOfDay()),
            )
            ->when(
                filled($filters['actor_user_id'] ?? null),
                fn ($builder) => $builder->where('actor_user_id', (int) $filters['actor_user_id']),
            )
            ->when(
                filled($filters['event'] ?? null),
                fn ($builder) => $builder->where('event', $filters['event']),
            )
            ->when(filled($filters['search'] ?? null), function ($builder) use ($filters): void {
                $search = '%'.trim($filters['search']).'%';

                $builder->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('description', 'like', $search)
                        ->orWhere('actor_name', 'like', $search)
                        ->orWhere('actor_email', 'like', $search)
                        ->orWhere('subject_label', 'like', $search)
                        ->orWhere('event', 'like', $search);
                });
            });

        return $query
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }
}
