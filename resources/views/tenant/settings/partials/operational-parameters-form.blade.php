<form method="POST" action="{{ route('tenant.settings.operational-parameters.update') }}" class="grid gap-4">
    @csrf
    @method('PUT')

    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Pausa minima em minutos</span>
            <input type="number" min="0" max="480" name="min_pause_minutes" value="{{ old('min_pause_minutes', $operationalParameter->min_pause_minutes) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Pausa maxima em minutos</span>
            <input type="number" min="1" max="1440" name="max_pause_minutes" value="{{ old('max_pause_minutes', $operationalParameter->max_pause_minutes) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Alerta de pausa longa</span>
            <input type="number" min="1" max="1440" name="pause_alert_minutes" value="{{ old('pause_alert_minutes', $operationalParameter->pause_alert_minutes) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Alerta de chamada longa</span>
            <input type="number" min="1" max="1440" name="call_alert_minutes" value="{{ old('call_alert_minutes', $operationalParameter->call_alert_minutes) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
    </div>

    <div class="grid gap-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="auto_available_after_call" value="0">
            <input type="checkbox" name="auto_available_after_call" value="1" @checked(old('auto_available_after_call', $operationalParameter->auto_available_after_call)) class="rounded border" style="border-color: var(--border);">
            <span class="font-semibold">Agente volta para disponivel apos tabular</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="allow_manual_calls_while_paused" value="0">
            <input type="checkbox" name="allow_manual_calls_while_paused" value="1" @checked(old('allow_manual_calls_while_paused', $operationalParameter->allow_manual_calls_while_paused)) class="rounded border" style="border-color: var(--border);">
            <span class="font-semibold">Permitir chamada manual durante pausa</span>
        </label>
    </div>

    <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border); background: color-mix(in srgb, var(--primary) 8%, var(--surface)); color: var(--fg-muted);">
        A pausa minima bloqueia retorno para disponivel antes do tempo configurado. A pausa maxima e os limites de alerta aparecem na supervisao e geram notificacoes operacionais.
    </div>

    <div class="flex justify-end gap-2">
        <x-ui.button type="submit">Salvar parametros</x-ui.button>
    </div>
</form>
