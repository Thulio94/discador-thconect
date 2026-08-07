<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\CallArchive;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchiveOldCalls extends Command
{
    protected $signature = 'calls:archive-old';
    protected $description = 'Move chamadas fora da janela operacional para arquivos JSONL externos.';

    public function handle(): int
    {
        $disk = (string) config('filesystems.archive_disk', 's3');
        if ($disk === 's3' && blank(config('filesystems.disks.s3.bucket'))) {
            $this->warn('Arquivo externo nao configurado; nenhuma chamada foi removida do banco.');
            return self::SUCCESS;
        }

        Company::query()->with('resourceLimit')->each(function (Company $company) use ($disk): void {
            $cutoff = now()->subMonths($company->resourceLimit?->hot_history_months ?? 24)->startOfMonth();
            $oldest = Call::query()->where('company_id', $company->id)->whereIn('status', ['ended', 'cancelled'])->where('started_at', '<', $cutoff)->oldest('started_at')->value('started_at');
            if (! $oldest) return;
            $month = now()->parse($oldest)->startOfMonth();
            while ($month->lt($cutoff)) {
                $this->archiveMonth($company->id, $month->copy(), $disk);
                $month->addMonth();
            }
        });
        return self::SUCCESS;
    }

    private function archiveMonth(int $companyId, $month, string $disk): void
    {
        if (CallArchive::query()->where('company_id', $companyId)->whereDate('period_start', $month)->exists()) return;
        $end = $month->copy()->endOfMonth();
        $query = Call::query()->where('company_id', $companyId)->whereIn('status', ['ended', 'cancelled'])->whereBetween('started_at', [$month, $end]);
        if (! $query->exists()) return;

        $temporary = tempnam(sys_get_temp_dir(), 'thconect-archive-'); $stream = fopen($temporary, 'w'); $count = 0;
        foreach ((clone $query)->orderBy('id')->lazyById(1000) as $call) { fwrite($stream, $call->toJson(JSON_UNESCAPED_UNICODE)."\n"); $count++; }
        fclose($stream); $checksum = hash_file('sha256', $temporary);
        $path = sprintf('call-archives/%d/%s.jsonl', $companyId, $month->format('Y-m'));
        $read = fopen($temporary, 'r'); Storage::disk($disk)->writeStream($path, $read, ['visibility' => 'private']); fclose($read); @unlink($temporary);
        DB::transaction(function () use ($query, $companyId, $month, $end, $disk, $path, $count, $checksum): void {
            CallArchive::create(['company_id' => $companyId, 'period_start' => $month, 'period_end' => $end, 'disk' => $disk, 'path' => $path, 'records_count' => $count, 'checksum' => $checksum, 'archived_at' => now()]);
            (clone $query)->delete();
        });
    }
}
