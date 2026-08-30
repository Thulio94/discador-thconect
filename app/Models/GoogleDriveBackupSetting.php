<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleDriveBackupSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'enabled' => 'boolean',
            'delete_local_after_backup' => 'boolean',
            'last_dispatched_on' => 'date',
            'last_succeeded_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'root_folder_name' => 'THCONECT - Backup de Gravacoes',
            'daily_at' => '23:30',
            'enabled' => false,
            'delete_local_after_backup' => true,
        ]);
    }

    public function isConnected(): bool
    {
        return filled($this->client_id) && filled($this->client_secret) && filled($this->refresh_token);
    }
}
