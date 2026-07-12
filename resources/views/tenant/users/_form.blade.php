@props(['user' => null, 'roles' => collect(), 'teams' => collect()])

<div class="grid gap-4 md:grid-cols-2">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $user?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">E-mail</span>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Ramal</span>
        <input name="extension" value="{{ old('extension', $user?->extension) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Tipo</span>
        <select name="user_type" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            @foreach (['agent' => 'Agente', 'supervisor' => 'Supervisor', 'company_admin' => 'Administrador'] as $value => $label)
                <option value="{{ $value }}" @selected(old('user_type', $user?->user_type ?? 'agent') === $value)>{{ $label }}</option>
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

<div class="mt-4 grid gap-2">
    <p class="text-sm font-semibold">Equipes</p>
    <div class="grid gap-2 md:grid-cols-2">
        @foreach ($teams as $team)
            <label class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, old('team_ids', $user?->assignedTeams->pluck('id')->all() ?? []), true))>
                <span>{{ $team->name }}</span>
            </label>
        @endforeach
    </div>
</div>
