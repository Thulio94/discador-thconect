@props(['role' => null, 'modules' => []])

@php
    $selectedPermissions = old('permissions', $role?->permissions->pluck('key')->all() ?? []);
@endphp

<div class="grid gap-4">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $role?->name) }}" class="rounded-lg border px-3 py-2" required>
    </label>
    <label class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
        <input type="hidden" name="is_admin" value="0">
        <input type="checkbox" name="is_admin" value="1" @checked((bool) old('is_admin', $role?->is_admin ?? false))>
        <span>Perfil administrativo</span>
    </label>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($modules as $module)
            <div class="rounded-lg border p-3" style="border-color: var(--border);">
                <p class="mb-2 text-sm font-semibold">{{ $module['label'] }}</p>
                <div class="grid gap-2">
                    @foreach ($module['permissions'] as $permission)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" @checked(in_array($permission['key'], $selectedPermissions, true))>
                            <span>{{ $permission['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
