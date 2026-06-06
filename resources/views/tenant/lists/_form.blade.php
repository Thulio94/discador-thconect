@php
    $list = $list ?? null;
    $contactsValue = old('contacts', $list?->contacts?->map(fn ($contact) => trim(($contact->name ? $contact->name.';' : '').$contact->phone))->implode("\n") ?? '');
@endphp

<div class="grid gap-4">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $list?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>

    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Descricao</span>
        <textarea name="description" rows="2" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">{{ old('description', $list?->description) }}</textarea>
    </label>

    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Contatos</span>
        <span class="text-xs" style="color: var(--fg-muted);">Use um contato por linha. Numeros duplicados, invalidos ou na blacklist ativa serao ignorados.</span>
        <textarea
            name="contacts"
            rows="7"
            class="rounded-lg border px-3 py-2 font-mono text-xs"
            style="border-color: var(--border); background: var(--surface); color: var(--fg);"
            placeholder="Maria;31999990001&#10;Joao;31999990002"
        >{{ $contactsValue }}</textarea>
    </label>

    <input type="hidden" name="status" value="{{ old('status', $list?->status ?? 'active') }}">
</div>
