@php($number ??= null)
@php($method ??= 'POST')

<form method="POST" action="{{ $action }}" class="grid gap-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Telefone</span>
            <input name="phone" value="{{ old('phone', $number?->phone) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="active" @selected(old('status', $number?->status ?? 'active') === 'active')>Ativo</option>
                <option value="inactive" @selected(old('status', $number?->status) === 'inactive')>Inativo</option>
            </select>
        </label>
    </div>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Motivo</span>
        <input name="reason" value="{{ old('reason', $number?->reason) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <div class="flex justify-end gap-2">
        <x-ui.button type="submit">Salvar</x-ui.button>
    </div>
</form>
