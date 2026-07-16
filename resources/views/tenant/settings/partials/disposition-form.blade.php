@php($disposition ??= null)
@php($method ??= 'POST')

<form method="POST" action="{{ $action }}" class="grid gap-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Nome</span>
            <input name="name" value="{{ old('name', $disposition?->name) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
        <div class="grid gap-1 text-sm">
            <span class="font-semibold">Codigo</span>
            <div class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);">
                {{ $disposition?->code ?? 'Gerado automaticamente ao salvar' }}
            </div>
        </div>
    </div>
    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Categoria</span>
            <select name="category" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                @foreach (['success' => 'Sucesso', 'neutral' => 'Neutra', 'negative' => 'Negativa', 'unproductive' => 'Improdutiva', 'callback' => 'Retorno'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', $disposition?->category ?? 'neutral') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="active" @selected(old('status', $disposition?->status ?? 'active') === 'active')>Ativa</option>
                <option value="inactive" @selected(old('status', $disposition?->status) === 'inactive')>Inativa</option>
            </select>
        </label>
    </div>
    <div class="grid gap-2 md:grid-cols-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="ends_call" value="0">
            <input type="checkbox" name="ends_call" value="1" @checked(old('ends_call', $disposition?->ends_call ?? true)) class="rounded border" style="border-color: var(--border);">
            <span class="font-semibold">Encerra chamada</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_success" value="0">
            <input type="checkbox" name="is_success" value="1" @checked(old('is_success', $disposition?->is_success ?? false)) class="rounded border" style="border-color: var(--border);">
            <span class="font-semibold">Conta como sucesso</span>
        </label>
    </div>
    <div class="grid gap-3 md:grid-cols-3">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="retry_enabled" value="0">
            <input type="checkbox" name="retry_enabled" value="1" @checked(old('retry_enabled', $disposition?->retry_enabled ?? false)) class="rounded border" style="border-color: var(--border);">
            <span class="font-semibold">Volta para fila</span>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Intervalo para nova tentativa (minutos)</span>
            <input type="number" min="0" max="10080" name="retry_delay_minutes" value="{{ old('retry_delay_minutes', $disposition?->retry_delay_minutes) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <span class="text-xs" style="color: var(--fg-muted);">Tempo minimo antes de o mesmo contato voltar para a fila de discagem.</span>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Max tentativas</span>
            <input type="number" min="1" max="50" name="max_attempts" value="{{ old('max_attempts', $disposition?->max_attempts) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
    </div>
    <div class="flex justify-end gap-2">
        <x-ui.button type="submit">Salvar</x-ui.button>
    </div>
</form>
