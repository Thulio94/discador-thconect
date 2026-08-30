<?php

namespace App\Jobs;

use App\Models\Call;
use App\Models\GoogleDriveBackupSetting;
use App\Models\RecordingBackup;
use App\Services\Recordings\GoogleDriveRecordingBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BackupCallRecordingToGoogleDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 900;

    public function __construct(public readonly int $callId)
    {
        $this->onQueue('reports');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("google-drive-recording:{$this->callId}"))->expireAfter(1200)];
    }

    public function handle(GoogleDriveRecordingBackup $drive): void
    {
        $setting = GoogleDriveBackupSetting::current();
        $call = Call::query()->with('company')->find($this->callId);
        if (! $call || $call->recording_disk !== 'recordings' || blank($call->recording_path) || ! $setting->enabled || ! $setting->isConnected()) {
            return;
        }

        $backup = RecordingBackup::query()->firstOrCreate(
            ['call_id' => $call->id],
            ['company_id' => $call->company_id, 'status' => 'pending'],
        );
        if ($backup->status === 'completed') {
            return;
        }

        $backup->increment('attempts_count');
        $backup->update(['status' => 'running', 'last_error' => null]);

        try {
            $localPath = $call->recording_path;
            $fileId = $drive->backup($call);
            $size = Storage::disk('recordings')->size($localPath);
            $checksum = hash_file('sha256', Storage::disk('recordings')->path($localPath));
            $drivePath = sprintf('%s/%s/%s', $call->company_id, ($call->started_at ?? now())->format('Y-m-d'), $fileId);

            $backup->update([
                'status' => 'completed',
                'drive_file_id' => $fileId,
                'drive_path' => $drivePath,
                'file_size' => $size,
                'checksum' => $checksum,
                'uploaded_at' => now(),
            ]);
            $call->update(['recording_disk' => 'google_drive', 'recording_path' => $fileId, 'recording_size' => $size]);
            if ($setting->delete_local_after_backup) {
                Storage::disk('recordings')->delete($localPath);
            }
            $setting->update(['last_succeeded_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $backup->update(['status' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 1000, '')]);
            $setting->update(['last_error' => Str::limit($exception->getMessage(), 1000, '')]);
            throw $exception;
        }
    }
}
