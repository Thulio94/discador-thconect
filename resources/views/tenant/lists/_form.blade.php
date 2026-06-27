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

    @if ($list)
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
    @else
        <div class="grid gap-4">
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Arquivo de contatos</span>
                <input type="file" name="import_file" accept=".csv,.txt,text/csv,text/plain" class="sr-only" x-ref="file" x-on:change="choose($event)">
                <button
                    type="button"
                    class="th-focus rounded-lg border border-dashed px-4 py-8 text-center text-sm transition"
                    style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);"
                    x-on:click="$refs.file.click()"
                    x-on:dragover.prevent
                    x-on:drop.prevent="drop($event)"
                >
                    Fazer upload CSV/TXT: arraste e solte ou clique para selecionar
                </button>
                <span class="text-xs" style="color: var(--fg-muted);" x-text="fileName || 'Envie um arquivo .csv ou .txt com cabecalho na primeira linha.'"></span>
                <span class="text-xs" style="color: var(--status-danger);" x-show="error" x-text="error"></span>
                @error('import_file')
                    <span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>
                @enderror
                @error('import_columns')
                    <span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>
                @enderror
            </label>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Separador</span>
                    <input name="separator" x-model="separator" x-on:change="refreshPreview()" maxlength="4" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Delimitador</span>
                    <input name="delimiter" x-model="delimiter" x-on:change="refreshPreview()" maxlength="4" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                </label>
            </div>

            <p class="rounded-lg border p-3 text-sm" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);">
                Marque pelo menos uma coluna ativa como Telefone. As demais colunas ativas serao salvas como dados extras do lead.
            </p>

            <div x-show="columns.length > 0" class="overflow-x-auto rounded-lg border" style="border-color: var(--border);">
                <table class="min-w-full text-sm">
                    <thead style="background: var(--surface-alt); color: var(--fg-muted);">
                        <tr>
                            <th class="px-3 py-2 text-left">Usar</th>
                            <th class="px-3 py-2 text-left">Tipo da coluna</th>
                            <th class="px-3 py-2 text-left">Nome da coluna</th>
                            <th class="px-3 py-2 text-left">Linha 1</th>
                            <th class="px-3 py-2 text-left">Linha 2</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(column, position) in columns" :key="column.index">
                            <tr class="border-t" style="border-color: var(--border);">
                                <td class="px-3 py-2 align-top">
                                    <input type="hidden" x-bind:name="`import_columns[${position}][index]`" x-bind:value="column.index">
                                    <input
                                        type="checkbox"
                                        value="1"
                                        x-bind:name="`import_columns[${position}][enabled]`"
                                        x-bind:aria-label="`Usar coluna ${position + 1}: ${column.name || 'sem nome'}`"
                                        x-model="column.enabled"
                                        class="rounded border"
                                        style="border-color: var(--border);"
                                    >
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <select
                                        x-bind:name="`import_columns[${position}][type]`"
                                        x-bind:aria-label="`Tipo da coluna ${position + 1}: ${column.name || 'sem nome'}`"
                                        x-model="column.type"
                                        class="rounded-lg border px-2 py-1"
                                        style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                    >
                                        <option value="extra">Extra</option>
                                        <option value="phone">Telefone</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <input
                                        x-bind:name="`import_columns[${position}][name]`"
                                        x-bind:aria-label="`Nome da coluna ${position + 1}: ${column.name || 'sem nome'}`"
                                        x-model="column.name"
                                        class="w-44 rounded-lg border px-2 py-1"
                                        style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                    >
                                </td>
                                <td class="px-3 py-2 align-top font-mono text-xs" style="color: var(--fg-muted);" x-text="previewRows[0]?.[column.index] || '-'"></td>
                                <td class="px-3 py-2 align-top font-mono text-xs" style="color: var(--fg-muted);" x-text="previewRows[1]?.[column.index] || '-'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p class="text-xs" style="color: var(--status-danger);" x-show="columns.length > 0 && !canSubmit()">
                Selecione ao menos uma coluna ativa do tipo Telefone para criar a lista.
            </p>
        </div>
    @endif

    <input type="hidden" name="status" value="{{ old('status', $list?->status ?? 'active') }}">
</div>
