<?php

namespace App\Jobs;

use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoreCallRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(public int $callId) {}

    public function handle(): void
    {
        $call = Call::query()->find($this->callId);
        if (! $call?->recording_path || $call->recording_disk !== 'recordings') return;

        $targetDisk = (string) config('filesystems.recordings_disk', 'recordings');
        if ($targetDisk === 'recordings') return;

        $source = Storage::disk('recordings');
        if (! $source->exists($call->recording_path)) throw new RuntimeException('Arquivo de gravacao ainda nao esta disponivel no volume do Asterisk.');

        $targetPath = sprintf('recordings/%d/%s/%s', $call->company_id, $call->started_at?->format('Y/m') ?? now()->format('Y/m'), basename($call->recording_path));
        $stream = $source->readStream($call->recording_path);
        if (! is_resource($stream) || ! Storage::disk($targetDisk)->writeStream($targetPath, $stream, ['visibility' => 'private'])) {
            if (is_resource($stream)) fclose($stream);
            throw new RuntimeException('Falha ao armazenar gravacao no destino configurado.');
        }
        fclose($stream);
        $source->delete($call->recording_path);
        $call->update(['recording_disk' => $targetDisk, 'recording_path' => $targetPath, 'recording_size' => Storage::disk($targetDisk)->size($targetPath)]);
    }
}
