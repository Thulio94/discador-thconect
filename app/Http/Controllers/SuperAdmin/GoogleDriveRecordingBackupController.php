<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GoogleDriveBackupSetting;
use App\Models\RecordingBackup;
use App\Services\Audit\AuditLogService;
use App\Services\Recordings\GoogleDriveRecordingBackup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveRecordingBackupController extends Controller
{
    public function index(): View
    {
        return view('super-admin.recording-backup.index', [
            'setting' => GoogleDriveBackupSetting::current(),
            'backups' => RecordingBackup::query()->with('company')->latest()->paginate(15),
        ]);
    }

    public function update(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'root_folder_name' => ['required', 'string', 'max:120'],
            'daily_at' => ['required', 'date_format:H:i'],
            'delete_local_after_backup' => ['nullable', 'boolean'],
        ]);
        $setting = GoogleDriveBackupSetting::current();
        $setting->update([
            'root_folder_name' => $data['root_folder_name'],
            'daily_at' => $data['daily_at'],
            'delete_local_after_backup' => $request->boolean('delete_local_after_backup'),
            'enabled' => false,
        ]);
        $audit->record(
            event: 'recording_backup.google_drive_configured',
            action: 'updated',
            description: 'Configuracao privada de backup de gravacoes atualizada.',
            subject: $setting,
            metadata: ['root_folder_name' => $setting->root_folder_name, 'daily_at' => $setting->daily_at],
        );

        return redirect()->route('super-admin.recording-backup.index')->with('status', 'Configuração do Google Drive salva. Conecte a conta para ativar o backup.');
    }

    public function connect(Request $request, GoogleDriveRecordingBackup $drive): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('google-drive-backup-oauth-state', $state);

        try {
            return redirect()->away($drive->authorizationUrl(route('super-admin.recording-backup.callback'), $state));
        } catch (Throwable $exception) {
            return back()->withErrors(['google_drive' => $exception->getMessage()]);
        }
    }

    public function callback(Request $request, GoogleDriveRecordingBackup $drive, AuditLogService $audit): RedirectResponse
    {
        abort_unless(hash_equals((string) $request->session()->pull('google-drive-backup-oauth-state'), (string) $request->string('state')), 403);
        if ($request->filled('error')) {
            return redirect()->route('super-admin.recording-backup.index')->withErrors(['google_drive' => 'A autorização do Google Drive foi cancelada.']);
        }

        try {
            $drive->exchangeAuthorizationCode((string) $request->string('code'), route('super-admin.recording-backup.callback'));
            $audit->record('recording_backup.google_drive_connected', 'connected', 'Conta Google autorizada para backup privado de gravacoes.');

            return redirect()->route('super-admin.recording-backup.index')->with('status', 'Google Drive conectado. O backup diário está ativo.');
        } catch (Throwable $exception) {
            return redirect()->route('super-admin.recording-backup.index')->withErrors(['google_drive' => $exception->getMessage()]);
        }
    }

    public function run(AuditLogService $audit): RedirectResponse
    {
        Artisan::call('recordings:dispatch-google-drive-backup', ['--force' => true]);
        $audit->record('recording_backup.google_drive_dispatched', 'dispatched', 'Backup privado de gravacoes solicitado manualmente.');

        return redirect()->route('super-admin.recording-backup.index')->with('status', 'Gravações locais concluídas foram adicionadas à fila de backup.');
    }
}
