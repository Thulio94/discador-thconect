<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'name', 'code', 'category', 'status', 'ends_call', 'is_success', 'retry_enabled', 'retry_delay_minutes', 'max_attempts'])]
class Disposition extends Model
{
    protected $casts = [
        'ends_call' => 'boolean',
        'is_success' => 'boolean',
        'retry_enabled' => 'boolean',
        'retry_delay_minutes' => 'integer',
        'max_attempts' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
