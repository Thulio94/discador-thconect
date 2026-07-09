<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'min_pause_minutes',
    'max_pause_minutes',
    'pause_alert_minutes',
    'call_alert_minutes',
    'auto_available_after_call',
    'allow_manual_calls_while_paused',
])]
class OperationalParameter extends Model
{
    protected $casts = [
        'min_pause_minutes' => 'integer',
        'max_pause_minutes' => 'integer',
        'pause_alert_minutes' => 'integer',
        'call_alert_minutes' => 'integer',
        'auto_available_after_call' => 'boolean',
        'allow_manual_calls_while_paused' => 'boolean',
    ];

    public static function defaults(): array
    {
        return [
            'min_pause_minutes' => 0,
            'max_pause_minutes' => 120,
            'pause_alert_minutes' => 20,
            'call_alert_minutes' => 15,
            'auto_available_after_call' => true,
            'allow_manual_calls_while_paused' => false,
        ];
    }

    public static function forCompany(int $companyId): self
    {
        return self::firstOrNew(
            ['company_id' => $companyId],
            self::defaults()
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
