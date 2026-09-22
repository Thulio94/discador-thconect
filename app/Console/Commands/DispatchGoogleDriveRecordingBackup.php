<?php

namespace App\Console\Commands;

use App\Jobs\BackupCallRecordingToGoogleDrive;
use App\Models\Call;
use App\Models\GoogleDriveBackupSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DispatchGoogleDriveRecordingBackup extends Command
{
    protected $signature = 'recordings:dispatch-google-drive-backup {--force : Ignore the configured time}';

    protected $description = 'Enfileira as gravações locais concluídas para backup privado no Google Drive.';

    public function handle(): int
    {
        $setting = GoogleDriveBackupSetting::current();
        if (! $setting->enabled || ! $setting->isConnected()) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && (now()->format('H:i') !== substr((string) $setting->daily_at, 0, 5) || $setting->last_dispatched_on?->isToday())) {
            return self::SUCCESS;
        }

        $ids = Call::query()
            ->where('recording_disk', 'recordings')
            ->whereNotNull('recording_path')
            ->whereNotNull('recording_available_at')
            ->pluck('id');
        Bus::batch($ids->map(fn (int $id) => new BackupCallRecordingToGoogleDrive($id))->all())
            ->name('backup-gravacoes-google-drive-'.today()->toDateString())
            ->onQueue('reports')
            ->dispatch();
        $setting->update(['last_dispatched_on' => today(), 'last_error' => null]);
        $this->info("{$ids->count()} gravações enfileiradas.");

        return self::SUCCESS;
    }
}
