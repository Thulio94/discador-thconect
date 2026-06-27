<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'contact_list_id', 'name', 'phone', 'status', 'attempts_count', 'last_attempt_at', 'next_attempt_at', 'last_result', 'extra'])]
class Contact extends Model
{
    protected $casts = [
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'extra' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class)->orderBy('position');
    }
}
