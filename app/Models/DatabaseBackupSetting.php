<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseBackupSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'send_to_google_drive' => 'boolean', 'keep_local_copy' => 'boolean', 'last_dispatched_on' => 'date'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['daily_at' => '02:30', 'keep_local_copy' => true]);
    }
}
