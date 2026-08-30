<?php

namespace App\Services\Backups;

use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function create(DatabaseBackup $backup): void
    {
        $connection = config('database.connections.mysql');
        $path = 'database-backups/discador-'.now()->format('Ymd-His').'-'.$backup->id.'.sql.gz';
        Storage::disk('local')->makeDirectory('database-backups');
        $target = Storage::disk('local')->path($path);
        $command = sprintf('mariadb-dump --single-transaction --routines --events --hex-blob -h%s -P%s -u%s %s | gzip -9 > %s',
            escapeshellarg((string) $connection['host']), escapeshellarg((string) ($connection['port'] ?? 3306)), escapeshellarg((string) $connection['username']), escapeshellarg((string) $connection['database']), escapeshellarg($target));
        $process = Process::fromShellCommandline($command, null, ['MYSQL_PWD' => (string) $connection['password']]);
        $process->setTimeout(3600)->run();
        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);
            throw new RuntimeException('Não foi possível concluir o backup do banco de dados.');
        }
        $backup->update(['status' => 'completed', 'file_path' => $path, 'file_size' => filesize($target), 'checksum' => hash_file('sha256', $target), 'completed_at' => now()]);
    }

    public function restore(DatabaseBackup $backup): void
    {
        if ($backup->status !== 'completed' || ! $backup->file_path || ! Storage::disk('local')->exists($backup->file_path)) {
            throw new RuntimeException('O arquivo de backup não está disponível localmente para restauração.');
        }
        $connection = config('database.connections.mysql');
        $source = Storage::disk('local')->path($backup->file_path);
        if (! hash_equals((string) $backup->checksum, hash_file('sha256', $source))) {
            throw new RuntimeException('O checksum do backup não confere. A restauração foi bloqueada.');
        }
        $command = sprintf('gzip -dc %s | mariadb -h%s -P%s -u%s %s', escapeshellarg($source), escapeshellarg((string) $connection['host']), escapeshellarg((string) ($connection['port'] ?? 3306)), escapeshellarg((string) $connection['username']), escapeshellarg((string) $connection['database']));
        $process = Process::fromShellCommandline($command, null, ['MYSQL_PWD' => (string) $connection['password']]);
        $process->setTimeout(3600)->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('A restauração do banco falhou; os dados atuais não foram apagados automaticamente.');
        }
    }
}
