<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'contact_id', 'phone', 'column_name', 'position', 'is_primary', 'status', 'attempts_count', 'last_attempt_at', 'next_attempt_at'])]
class ContactPhone extends Model
{
    protected $casts = [
        'is_primary' => 'boolean',
        'position' => 'integer',
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
