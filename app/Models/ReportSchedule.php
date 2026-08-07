<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'user_id',
    'report_type',
    'name',
    'format',
    'frequency',
    'weekday',
    'run_time',
    'filters',
    'status',
    'last_run_at',
    'last_records_count',
])]
class ReportSchedule extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'weekday' => 'integer',
            'last_run_at' => 'datetime',
            'last_records_count' => 'integer',
        ];
    }
}
