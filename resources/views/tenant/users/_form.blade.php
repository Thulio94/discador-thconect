@props(['user' => null, 'roles' => collect(), 'teams' => collect(), 'campaigns' => collect()])

@php
    $selectedTeamIds = collect(old('team_ids', $user?->assignedTeams->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedCampaignIds = collect(old('campaign_ids', $user?->assignedCampaigns->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedType = old('user_type', $user?->user_type ?? 'agent');
@endphp

<div x-data="{ userType: '{{ $selectedType }}' }">
    <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Nome</span>
            <input name="name" value="{{ old('name', $user?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">E-mail</span>
            <input type="email" name="email" value="{{ old('email', $user?->email) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
        </label>
        <div class="grid gap-1 text-sm">
            <span class="font-semibold">Ramal telefonico</span>
            <span class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);">{{ $user?->extension ?? ($user?->status === 'inactive' ? 'Sem ramal: usuario inativo' : 'Sera gerado automaticamente') }}</span>
            <span class="text-xs" style="color: var(--fg-muted);">{{ $user ? 'Ao inativar, o ramal e liberado. Ao reativar, o sistema reserva outro numero disponivel.' : 'Usuarios ativos recebem um numero unico entre 1000 e 10000. Uma senha temporaria forte sera gerada e exibida uma unica vez.' }}</span>
        </div>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Tipo</span>
            <select name="user_type" x-model="userType" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                @foreach (['agent' => 'Agente', 'supervisor' => 'Supervisor', 'company_admin' => 'Administrador'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Perfil</span>
            <select name="role_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) old('role_id', $user?->role_id) === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="active" @selected(old('status', $user?->status ?? 'active') === 'active')>Ativo</option>
                <option value="inactive" @selected(old('status', $user?->status) === 'inactive')>Inativo</option>
            </select>
        </label>
    </div>

    @if ($user)
        <section class="mt-4 rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
            <p class="text-sm font-semibold">Alterar senha</p>
            <p class="mt-1 text-xs" style="color: var(--fg-muted);">Deixe os campos vazios para manter a senha atual.</p>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Nova senha</span>
                    <input type="password" name="password" autocomplete="new-password" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" minlength="8">
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Confirmar nova senha</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" minlength="8">
                </label>
            </div>
        </section>
    @endif

    <section x-show="userType !== 'company_admin'" x-cloak class="mt-4 grid gap-4">
        <div x-show="userType === 'agent'" x-cloak>
            <p class="mb-1 text-sm font-semibold">Equipe</p>
            <p class="mb-2 text-xs" style="color: var(--fg-muted);">Um agente pode participar de apenas uma equipe.</p>
            <select name="team_ids[]" x-bind:disabled="userType !== 'agent'" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Sem equipe definida</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) ($selectedTeamIds[0] ?? 0) === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="userType === 'supervisor'" x-cloak>
            <p class="mb-1 text-sm font-semibold">Equipes acompanhadas</p>
            <p class="mb-2 text-xs" style="color: var(--fg-muted);">O supervisor pode acompanhar mais de uma equipe.</p>
            <x-ui.multi-select name="team_ids[]" :options="$teams" :selected="$selectedTeamIds" placeholder="Selecionar equipes" disabled-when="userType !== 'supervisor'" />
        </div>

        <div x-show="userType === 'agent'" x-cloak>
            <p class="mb-1 text-sm font-semibold">Campanhas do agente</p>
            <p class="mb-2 text-xs" style="color: var(--fg-muted);">O agente pode participar de mais de uma campanha.</p>
            <x-ui.multi-select name="campaign_ids[]" :options="$campaigns" :selected="$selectedCampaignIds" placeholder="Selecionar campanhas" empty-message="Nenhuma campanha disponivel." disabled-when="userType !== 'agent'" />
        </div>
    </section>
</div>
