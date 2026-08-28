<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'service_script', 'service_fields_schema', 'dialing_mode', 'lead_phone_strategy', 'agent_distribution_strategy', 'max_attempts_per_contact', 'retry_delay_minutes', 'wrap_up_minutes', 'wrap_up_seconds', 'max_concurrent_calls', 'amd_mode', 'dialing_route', 'fixed_telephony_route_id', 'mobile_telephony_route_id', 'status', 'priority', 'operational_schedule', 'starts_at', 'ends_at'])]
class Campaign extends Model
{
    public const AGENT_DISTRIBUTION_STRATEGIES = ['least_idle', 'balanced', 'random'];

    protected $attributes = [
        'agent_distribution_strategy' => 'least_idle',
    ];

    protected $casts = [
        'service_fields_schema' => 'array',
        'operational_schedule' => 'array',
        'max_attempts_per_contact' => 'integer',
        'retry_delay_minutes' => 'integer',
        'wrap_up_minutes' => 'integer',
        'wrap_up_seconds' => 'integer',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user')
            ->withPivot('company_id')
            ->withTimestamps();
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function fixedTelephonyRoute(): BelongsTo
    {
        return $this->belongsTo(TelephonyRoute::class, 'fixed_telephony_route_id');
    }

    public function mobileTelephonyRoute(): BelongsTo
    {
        return $this->belongsTo(TelephonyRoute::class, 'mobile_telephony_route_id');
    }
}
