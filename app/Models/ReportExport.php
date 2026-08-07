<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'report_type', 'format', 'status', 'progress', 'filters', 'records_count', 'file_path', 'file_size', 'error_message', 'started_at', 'completed_at', 'expires_at'])]
class ReportExport extends Model
{
    protected $casts = [
        'filters' => 'array',
        'records_count' => 'integer',
        'progress' => 'integer',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
