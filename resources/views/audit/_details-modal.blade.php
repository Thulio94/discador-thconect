<x-ui.modal name="audit-detail-{{ $log->id }}" title="Detalhes da auditoria" max-width="2xl">
    @php
        $changedKeys = collect(array_keys($log->old_values ?? []))
            ->merge(array_keys($log->new_values ?? []))
            ->unique();
    @endphp
    <div class="grid gap-4 text-sm">
        <div class="grid gap-3 sm:grid-cols-2">
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Evento</p><p class="mt-1">{{ $log->event }}</p></div>
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Data</p><p class="mt-1">{{ $log->created_at?->format('d/m/Y H:i:s') }}</p></div>
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Responsavel</p><p class="mt-1">{{ $log->actor_name ?? 'Sistema' }}</p></div>
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Entidade</p><p class="mt-1">{{ $log->subject_label ?? 'Nao informado' }}</p></div>
        </div>

        @if ($changedKeys->isNotEmpty())
            <div class="overflow-hidden rounded-lg border" style="border-color: var(--border);">
                <div class="grid grid-cols-[minmax(120px,0.7fr)_minmax(0,1fr)_minmax(0,1fr)] gap-2 px-3 py-2 text-xs font-semibold uppercase" style="background: var(--bg-subtle); color: var(--fg-muted);">
                    <span>Campo</span><span>Anterior</span><span>Novo</span>
                </div>
                @foreach ($changedKeys as $key)
                    @php
                        $oldValue = data_get($log->old_values, $key);
                        $newValue = data_get($log->new_values, $key);
                        $oldDisplay = is_scalar($oldValue) ? (string) $oldValue : (json_encode($oldValue, JSON_UNESCAPED_UNICODE) ?: 'Nao informado');
                        $newDisplay = is_scalar($newValue) ? (string) $newValue : (json_encode($newValue, JSON_UNESCAPED_UNICODE) ?: 'Nao informado');
                    @endphp
                    <div class="grid grid-cols-[minmax(120px,0.7fr)_minmax(0,1fr)_minmax(0,1fr)] gap-2 border-t px-3 py-2" style="border-color: var(--border);">
                        <span class="font-medium">{{ $key }}</span>
                        <span class="break-words">{{ $oldDisplay }}</span>
                        <span class="break-words">{{ $newDisplay }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2">
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">IP</p><p class="mt-1">{{ $log->ip_address ?? 'Nao informado' }}</p></div>
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Rota</p><p class="mt-1">{{ $log->http_method }} {{ $log->route_name ?? 'Nao informado' }}</p></div>
        </div>
        @if ($log->metadata)
            <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Contexto</p><pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap rounded-lg border p-3 text-xs" style="border-color: var(--border); background: var(--bg-subtle);">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
        @endif
        <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Navegador</p><p class="mt-1 break-words">{{ $log->user_agent ?? 'Nao informado' }}</p></div>
    </div>
</x-ui.modal>
