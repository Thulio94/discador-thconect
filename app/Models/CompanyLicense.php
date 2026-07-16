<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'status',
    'starts_at',
    'ends_at',
    'max_users',
    'max_concurrent_agents',
    'max_concurrent_logged_agents',
    'max_calls_per_available_agent',
    'max_concurrent_calls',
    'power_enabled',
    'predictive_enabled',
    'preview_enabled',
    'amd_enabled',
])]
class CompanyLicense extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'max_users' => 'integer',
            'max_concurrent_agents' => 'integer',
            'max_concurrent_logged_agents' => 'integer',
            'max_calls_per_available_agent' => 'integer',
            'max_concurrent_calls' => 'integer',
            'power_enabled' => 'boolean',
            'predictive_enabled' => 'boolean',
            'preview_enabled' => 'boolean',
            'amd_enabled' => 'boolean',
        ];
    }
}
