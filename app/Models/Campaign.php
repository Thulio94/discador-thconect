<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'service_script', 'dialing_mode', 'status', 'priority', 'operational_schedule', 'starts_at', 'ends_at'])]
class Campaign extends Model
{
    protected $casts = [
        'operational_schedule' => 'array',
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
