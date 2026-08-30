<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\ReportExport;
use App\Services\Recordings\GoogleDriveRecordingBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredReportAssets extends Command
{
    protected $signature = 'reports:purge-expired-assets';

    protected $description = 'Remove arquivos de relatório e gravações que passaram da retenção configurada.';

    public function handle(GoogleDriveRecordingBackup $drive): int
    {
        $exports = 0;
        ReportExport::query()->where('status', 'completed')->whereNotNull('expires_at')->where('expires_at', '<=', now())->each(function (ReportExport $export) use (&$exports): void {
            if ($export->file_path) {
                Storage::disk('local')->delete($export->file_path);
            }
            $export->update(['status' => 'expired', 'file_path' => null, 'file_size' => null]);
            $exports++;
        });

        $recordings = 0;
        Call::query()->whereNotNull('recording_path')->with('company')->each(function (Call $call) use (&$recordings): void {
            $days = $call->company?->recording_retention_days;
            if ($days === null || ! $call->recording_available_at || $call->recording_available_at->addDays($days)->isFuture()) {
                return;
            }
            if ($call->recording_disk === 'google_drive') {
                $drive->delete($call->recording_path);
            } else {
                Storage::disk($call->recording_disk ?: 'local')->delete($call->recording_path);
            }
            $call->update(['recording_path' => null, 'recording_disk' => null, 'recording_size' => null]);
            $recordings++;
        });

        $this->info(sprintf('%d exportações e %d gravações removidas.', $exports, $recordings));

        return self::SUCCESS;
    }
}
