<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Models\DatabaseBackupSetting;
use App\Services\Backups\DatabaseBackupService;
use Illuminate\Console\Command;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'system:database-backup {--force}';

    protected $description = 'Cria um backup comprimido e verificável do banco de dados.';

    public function handle(DatabaseBackupService $service): int
    {
        $settings = DatabaseBackupSetting::current();
        if (! $this->option('force') && (! $settings->enabled || now()->format('H:i') !== substr((string) $settings->daily_at, 0, 5) || $settings->last_dispatched_on?->isToday())) {
            return self::SUCCESS;
        }
        $backup = DatabaseBackup::create(['status' => 'running']);
        try {
            $service->create($backup);
            $settings->update(['last_dispatched_on' => today(), 'last_error' => null]);
            $this->info('Backup concluído.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $backup->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            $settings->update(['last_error' => $e->getMessage()]);
            $this->error('Backup falhou.');

            return self::FAILURE;
        }
    }
}
