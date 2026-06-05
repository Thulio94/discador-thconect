@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $classes = [
        'primary' => 'background: var(--primary); color: #fff;',
        'secondary' => 'background: var(--surface-alt); color: var(--fg); border: 1px solid var(--border);',
        'ghost' => 'background: transparent; color: var(--fg);',
        'danger' => 'background: var(--status-danger); color: #fff;',
    ][$variant] ?? 'background: var(--primary); color: var(--on-primary);';
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'th-focus inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40']) }}
    style="{{ $classes }} border-radius: var(--radius-md);"
>
    {{ $slot }}
</button>
