@php($reason ??= null)
@php($method ??= 'POST')

<form method="POST" action="{{ $action }}" class="grid gap-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $reason?->name) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Descricao</span>
        <textarea name="description" rows="3" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">{{ old('description', $reason?->description) }}</textarea>
    </label>
    <div class="grid gap-3 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="active" @selected(old('status', $reason?->status ?? 'active') === 'active')>Ativo</option>
                <option value="inactive" @selected(old('status', $reason?->status) === 'inactive')>Inativo</option>
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_billable" value="0">
            <input type="checkbox" name="is_billable" value="1" @checked(old('is_billable', $reason?->is_billable ?? false)) class="rounded border" style="border-color: var(--border);">
            <span class="font-semibold">Conta como pausa produtiva</span>
        </label>
    </div>
    <div class="flex justify-end gap-2">
        <x-ui.button type="submit">Salvar</x-ui.button>
    </div>
</form>
