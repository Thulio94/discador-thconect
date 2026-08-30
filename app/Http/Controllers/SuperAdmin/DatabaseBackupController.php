<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseBackup;
use App\Models\DatabaseBackupSetting;
use App\Services\Audit\AuditLogService;
use App\Services\Backups\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupController extends Controller
{
    public function index()
    {
        return view('super-admin.database-backup.index', ['setting' => DatabaseBackupSetting::current(), 'backups' => DatabaseBackup::query()->latest()->paginate(15)]);
    }

    public function update(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['nullable', 'boolean'], 'daily_at' => ['required', 'date_format:H:i'], 'keep_local_copy' => ['nullable', 'boolean']]);
        DatabaseBackupSetting::current()->update(['enabled' => $request->boolean('enabled'), 'daily_at' => $data['daily_at'], 'keep_local_copy' => $request->boolean('keep_local_copy')]);
        $audit->record('database_backup.configured', 'updated', 'Agenda de backup de dados atualizada.');

        return back()->with('status', 'Agenda de backup atualizada.');
    }

    public function run(AuditLogService $audit): RedirectResponse
    {
        Artisan::call('system:database-backup', ['--force' => true]);
        $audit->record('database_backup.created', 'requested', 'Backup de dados solicitado pelo super admin.');

        return back()->with('status', 'Backup iniciado. Atualize a página para acompanhar o resultado.');
    }

    public function download(DatabaseBackup $backup)
    {
        abort_unless($backup->status === 'completed' && $backup->file_path && Storage::disk('local')->exists($backup->file_path), 404);

        return Storage::disk('local')->download($backup->file_path, 'discador-backup-'.$backup->id.'.sql.gz');
    }

    public function restore(Request $request, DatabaseBackup $backup, DatabaseBackupService $service, AuditLogService $audit): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:RESTAURAR']]);
        $safetyBackup = DatabaseBackup::create(['status' => 'running', 'created_by_user_id' => $request->user()->id]);
        $service->create($safetyBackup);
        Artisan::call('down', ['--retry' => 60]);
        try {
            $service->restore($backup);
            $backup->update(['restored_at' => now(), 'restored_by_user_id' => $request->user()->id]);
            $audit->record('database_backup.restored', 'restored', 'Banco restaurado a partir de backup confirmado.', subject: $backup);
        } finally {
            Artisan::call('up');
        }

        return redirect()->route('login')->with('status', 'Restauração concluída. Entre novamente no sistema.');
    }
}
