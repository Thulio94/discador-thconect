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
            ->withPivot('membership_type')
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
}
