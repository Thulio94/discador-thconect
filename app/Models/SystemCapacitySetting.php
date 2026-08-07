<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'max_channels', 'warning_percent', 'critical_percent', 'cpu_warning_percent',
    'cpu_critical_percent', 'memory_warning_percent', 'memory_critical_percent',
    'database_warning_percent', 'database_critical_percent',
    'telephony_queue_warning_ms', 'telephony_queue_block_ms', 'origination_blocked',
])]
class SystemCapacitySetting extends Model
{
    protected function casts(): array
    {
        return ['origination_blocked' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['max_channels' => 500]);
    }
}
