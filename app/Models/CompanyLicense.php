<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'status',
    'starts_at',
    'ends_at',
    'billing_due_day',
    'max_users',
    'max_concurrent_agents',
    'max_calls_per_available_agent',
    'max_concurrent_calls',
    'power_enabled',
    'predictive_enabled',
    'preview_enabled',
    'amd_enabled',
])]
class CompanyLicense extends Model
{
    public function nextBillingDate(?CarbonInterface $reference = null): CarbonInterface
    {
        $reference ??= today();
        $dueDay = min(28, max(1, (int) ($this->billing_due_day ?? $this->ends_at?->day ?? 1)));
        $dueDate = $reference->copy()->startOfMonth()->setDay($dueDay);

        return $dueDate->isBefore($reference->copy()->startOfDay())
            ? $dueDate->addMonth()
            : $dueDate;
    }

    /**
     * @return array{due_at: CarbonInterface, days_until: int, is_due_soon: bool}
     */
    public function billingNotice(int $noticeDays = 10, ?CarbonInterface $reference = null): array
    {
        $reference ??= today();
        $dueAt = $this->nextBillingDate($reference);
        $daysUntil = (int) $reference->copy()->startOfDay()->diffInDays($dueAt, false);

        return [
            'due_at' => $dueAt,
            'days_until' => $daysUntil,
            'is_due_soon' => $daysUntil <= $noticeDays,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'billing_due_day' => 'integer',
            'max_users' => 'integer',
            'max_concurrent_agents' => 'integer',
            'max_calls_per_available_agent' => 'integer',
            'max_concurrent_calls' => 'integer',
            'power_enabled' => 'boolean',
            'predictive_enabled' => 'boolean',
            'preview_enabled' => 'boolean',
            'amd_enabled' => 'boolean',
        ];
    }
}
