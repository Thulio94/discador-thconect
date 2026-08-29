@props(['team' => null, 'agents' => collect(), 'supervisors' => collect()])

@php
    $selectedAgents = collect(old('agent_ids', $team?->members->where('pivot.membership_type', 'agent')->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedSupervisors = collect(old('supervisor_ids', $team?->members->where('pivot.membership_type', 'supervisor')->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="grid gap-4">
    <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Nome</span>
            <input name="name" value="{{ old('name', $team?->name) }}" class="rounded-lg border px-3 py-2" required>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2">
                <option value="active" @selected(old('status', $team?->status ?? 'active') === 'active')>Ativa</option>
                <option value="inactive" @selected(old('status', $team?->status) === 'inactive')>Inativa</option>
            </select>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Tipo operacional</span>
            <select name="operational_type" class="rounded-lg border px-3 py-2">
                @foreach (['power' => 'Power', 'preview' => 'Preview', 'pbx' => 'PABX'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('operational_type', $team?->operational_type ?? 'power') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="allow_agent_campaign_selection" value="1" @checked(old('allow_agent_campaign_selection', $team?->allow_agent_campaign_selection ?? false))>
        <span>Permitir que agentes escolham a campanha ao ficar disponíveis</span>
    </label>

    <div class="grid gap-3 md:grid-cols-2">
        <div x-data="{ search: '' }">
            <p class="mb-2 text-sm font-semibold">Agentes</p>
            <input x-model="search" type="search" placeholder="Buscar nome, ramal ou e-mail" class="mb-2 w-full rounded-lg border px-3 py-2 text-sm">
            <div class="grid max-h-64 gap-2 overflow-y-auto pr-1">
                @forelse ($agents as $agent)
                    <label x-show="@js($agent->name.' '.$agent->extension.' '.$agent->email).toLowerCase().includes(search.toLowerCase())" class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                        <input type="checkbox" name="agent_ids[]" value="{{ $agent->id }}" @checked(in_array($agent->id, $selectedAgents, true))>
                        <span>{{ $agent->name }} · Ramal {{ $agent->extension ?: '-' }} · {{ $agent->email }}</span>
                    </label>
                @empty
                    <p class="text-xs" style="color: var(--fg-muted);">Nenhum agente cadastrado.</p>
                @endforelse
            </div>
        </div>
        <div>
            <p class="mb-2 text-sm font-semibold">Supervisores</p>
            <div class="grid max-h-64 gap-2 overflow-y-auto pr-1">
                @forelse ($supervisors as $supervisor)
                    <label class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                        <input type="checkbox" name="supervisor_ids[]" value="{{ $supervisor->id }}" @checked(in_array($supervisor->id, $selectedSupervisors, true))>
                        <span>{{ $supervisor->name }}</span>
                    </label>
                @empty
                    <p class="text-xs" style="color: var(--fg-muted);">Nenhum supervisor cadastrado.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
