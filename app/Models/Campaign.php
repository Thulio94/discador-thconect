<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'service_script', 'service_fields_schema', 'dialing_mode', 'lead_phone_strategy', 'max_attempts_per_contact', 'retry_delay_minutes', 'wrap_up_minutes', 'max_concurrent_calls', 'amd_mode', 'dialing_route', 'status', 'priority', 'operational_schedule', 'starts_at', 'ends_at'])]
class Campaign extends Model
{
    protected $casts = [
        'service_fields_schema' => 'array',
        'operational_schedule' => 'array',
        'max_attempts_per_contact' => 'integer',
        'retry_delay_minutes' => 'integer',
        'wrap_up_minutes' => 'integer',
        'max_concurrent_calls' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('company_id')
            ->withTimestamps();
    }

    public function contactLists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'campaign_contact_list')
            ->withPivot('company_id', 'weight')
            ->withTimestamps();
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
