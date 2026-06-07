@php
    $campaign = $campaign ?? null;
    $selectedTeamIds = collect(old('team_ids', $campaign?->teams?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedListIds = collect(old('list_ids', $campaign?->contactLists?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="grid gap-4">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $campaign?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>

    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Descricao</span>
        <textarea name="description" rows="2" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">{{ old('description', $campaign?->description) }}</textarea>
    </label>

    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Script de atendimento</span>
        <textarea name="service_script" rows="4" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" placeholder="Oriente o agente sobre abordagem, perguntas e proximos passos.">{{ old('service_script', $campaign?->service_script) }}</textarea>
    </label>

    <div class="grid gap-4 md:grid-cols-3">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Modo</span>
            <select name="dialing_mode" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="preview" @selected(old('dialing_mode', $campaign?->dialing_mode ?? 'preview') === 'preview')>Preview</option>
                <option value="manual" @selected(old('dialing_mode', $campaign?->dialing_mode) === 'manual')>Manual</option>
                <option value="power" @selected(old('dialing_mode', $campaign?->dialing_mode) === 'power')>Power</option>
                <option value="predictive" @selected(old('dialing_mode', $campaign?->dialing_mode) === 'predictive')>Preditivo</option>
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="draft" @selected(old('status', $campaign?->status ?? 'draft') === 'draft')>Rascunho</option>
                <option value="active" @selected(old('status', $campaign?->status) === 'active')>Ativa</option>
                <option value="paused" @selected(old('status', $campaign?->status) === 'paused')>Pausada</option>
                <option value="finished" @selected(old('status', $campaign?->status) === 'finished')>Finalizada</option>
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Prioridade</span>
            <input name="priority" type="number" min="1" max="10" value="{{ old('priority', $campaign?->priority ?? 1) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <p class="mb-2 text-sm font-semibold">Equipes</p>
            @forelse ($teams as $team)
                <label class="mb-2 flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, $selectedTeamIds, true))>
                    <span>{{ $team->name }}</span>
                </label>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Cadastre uma equipe antes.</p>
            @endforelse
        </div>

        <div>
            <p class="mb-2 text-sm font-semibold">Listas</p>
            @forelse ($lists as $list)
                <label class="mb-2 flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <input type="checkbox" name="list_ids[]" value="{{ $list->id }}" @checked(in_array($list->id, $selectedListIds, true))>
                    <span>{{ $list->name }}</span>
                </label>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Cadastre uma lista antes.</p>
            @endforelse
        </div>
    </div>
</div>
