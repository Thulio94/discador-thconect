<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Super admin</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Backup de gravações</h1>
        </div>
    </x-slot>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <section class="rounded-lg border p-5" style="border-color: var(--border); background: var(--surface);">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold">Google Drive privado</h2>
                    <p class="mt-1 text-xs" style="color: var(--fg-muted);">As empresas não visualizam esta configuração nem a localização dos arquivos.</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold" style="background: color-mix(in srgb, {{ $setting->isConnected() ? 'var(--status-success)' : 'var(--status-warning)' }} 16%, var(--surface)); color: {{ $setting->isConnected() ? 'var(--status-success)' : 'var(--status-warning)' }};">{{ $setting->isConnected() ? 'Conectado' : 'Não conectado' }}</span>
            </div>

            @if ($errors->has('google_drive'))
                <p class="mb-4 rounded-lg border px-3 py-2 text-xs" style="border-color: var(--status-danger); color: var(--status-danger);">{{ $errors->first('google_drive') }}</p>
            @endif

            <form method="POST" action="{{ route('super-admin.recording-backup.update') }}" class="grid gap-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm"><span class="font-semibold">Pasta principal</span><input name="root_folder_name" value="{{ old('root_folder_name', $setting->root_folder_name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface-alt);" required></label>
                    <label class="grid gap-1 text-sm"><span class="font-semibold">Horário diário</span><input type="time" name="daily_at" value="{{ old('daily_at', substr((string) $setting->daily_at, 0, 5)) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface-alt);" required></label>
                </div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="delete_local_after_backup" value="1" @checked(old('delete_local_after_backup', $setting->delete_local_after_backup))><span><strong>Remover cópia local após confirmação</strong><br><span class="text-xs" style="color: var(--fg-muted);">O arquivo só sai da VPS quando o Drive confirma o upload e o índice é atualizado.</span></span></label>
                <x-ui.button type="submit">Salvar configuração</x-ui.button>
            </form>

            <div class="mt-5 flex flex-wrap gap-2 border-t pt-5" style="border-color: var(--border);">
                <form method="POST" action="{{ route('super-admin.recording-backup.connect') }}">@csrf<x-ui.button type="submit" variant="secondary" :disabled="! $setting->isOAuthConfigured()">Conectar com Google</x-ui.button></form>
                <form method="POST" action="{{ route('super-admin.recording-backup.run') }}">@csrf<x-ui.button type="submit" variant="secondary" :disabled="! $setting->isConnected()">Executar backup agora</x-ui.button></form>
            </div>
            @if (! $setting->isOAuthConfigured())
                <p class="mt-4 text-[11px]" style="color: var(--status-warning);">A integração do Google ainda precisa ser ativada nas variáveis seguras do servidor.</p>
            @endif
        </section>

        <section class="rounded-lg border" style="border-color: var(--border); background: var(--surface);">
            <header class="border-b px-5 py-4" style="border-color: var(--border);"><h2 class="text-base font-bold">Índice de backups</h2><p class="mt-1 text-xs" style="color: var(--fg-muted);">Cada gravação confirma empresa, protocolo, tamanho e arquivo remoto.</p></header>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead style="background: var(--surface-alt); color: var(--fg-muted);"><tr><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Enviado</th><th class="px-4 py-3">Arquivo</th></tr></thead><tbody>
                @forelse ($backups as $backup)
                    <tr class="border-t" style="border-color: var(--border);"><td class="px-4 py-3 font-semibold">{{ $backup->company?->name }}</td><td class="px-4 py-3">{{ $backup->status }}</td><td class="px-4 py-3">{{ $backup->uploaded_at?->format('d/m/Y H:i') ?? '-' }}</td><td class="px-4 py-3 font-mono text-[10px]">{{ $backup->drive_file_id ? 'Indexado' : '-' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center" style="color: var(--fg-muted);">Nenhuma gravação enviada ainda.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="px-5 py-4">{{ $backups->links() }}</div>
        </section>
    </div>
</x-app-layout>
