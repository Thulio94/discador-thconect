@props(['company' => null])

@php
    $license = $company?->license;
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $company?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Documento</span>
        <input name="document" value="{{ old('document', $company?->document) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Status</span>
        <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <option value="active" @selected(old('status', $company?->status ?? 'active') === 'active')>Ativa</option>
            <option value="inactive" @selected(old('status', $company?->status) === 'inactive')>Inativa</option>
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Inicio da licenca</span>
        <input type="date" name="license_starts_at" value="{{ old('license_starts_at', $license?->starts_at?->toDateString() ?? now()->toDateString()) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Fim da licenca</span>
        <input type="date" name="license_ends_at" value="{{ old('license_ends_at', $license?->ends_at?->toDateString() ?? now()->addMonth()->toDateString()) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Chamadas por operador disponivel</span>
        <input type="number" min="1" max="20" name="max_calls_per_available_agent" value="{{ old('max_calls_per_available_agent', $license?->max_calls_per_available_agent ?? 1) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Chamadas simultaneas maximas</span>
        <input type="number" min="1" max="100000" name="max_concurrent_calls" value="{{ old('max_concurrent_calls', $license?->max_concurrent_calls) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
</div>

<div class="mt-4 grid gap-3 md:grid-cols-4">
    @foreach ([
        'power_enabled' => 'Power',
        'predictive_enabled' => 'Preditivo',
        'preview_enabled' => 'Preview',
        'amd_enabled' => 'AMD',
    ] as $field => $label)
        <label class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $license?->{$field} ?? $field === 'power_enabled' || $field === 'preview_enabled'))>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
