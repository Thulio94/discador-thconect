<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'status', 'import_status', 'import_progress', 'imported_rows', 'import_error', 'import_started_at', 'import_completed_at'])]
class ContactList extends Model
{
    protected function casts(): array
    {
        return ['import_started_at' => 'datetime', 'import_completed_at' => 'datetime'];
    }
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_contact_list')
            ->withPivot('company_id', 'weight')
            ->withTimestamps();
    }
}
