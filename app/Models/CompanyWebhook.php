<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'name', 'endpoint', 'secret', 'events', 'status', 'last_tested_at', 'last_response_status', 'last_error'])]
class CompanyWebhook extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'last_tested_at' => 'datetime',
            'last_response_status' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
