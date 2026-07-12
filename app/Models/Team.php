<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['company_id', 'name', 'slug', 'status'])]
class Team extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->where('users.company_id', $this->company_id)
            ->withPivotValue('company_id', $this->company_id)
            ->withPivot('membership_type')
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('company_id', 'membership_type')
            ->withTimestamps();
    }

    public function agents(): BelongsToMany
    {
        return $this->users()->wherePivot('membership_type', 'agent');
    }

    public function supervisors(): BelongsToMany
    {
        return $this->users()->wherePivot('membership_type', 'supervisor');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class)
            ->withPivot('company_id')
            ->withTimestamps();
    }
}
