@props([
    'as' => null,
    'href' => null,
    'icon' => 'open',
    'label',
    'showLabel' => false,
    'type' => 'button',
    'variant' => 'secondary',
])

@php
    $tag = $as ?? ($href ? 'a' : 'button');
    $variantStyle = [
        'primary' => 'border-color: var(--primary); background: var(--primary); color: #fff;',
        'secondary' => 'border-color: var(--border); background: var(--surface); color: var(--fg);',
        'ghost' => 'border-color: transparent; background: transparent; color: var(--fg-muted);',
        'warning' => 'border-color: color-mix(in srgb, var(--status-warning) 45%, var(--border)); background: color-mix(in srgb, var(--status-warning) 14%, var(--surface)); color: var(--fg);',
        'danger' => 'border-color: color-mix(in srgb, var(--status-danger) 50%, var(--border)); background: color-mix(in srgb, var(--status-danger) 12%, var(--surface)); color: var(--status-danger);',
    ][$variant] ?? 'border-color: var(--border); background: var(--surface); color: var(--fg);';
    $sizeClass = $showLabel ? 'min-h-10 px-3 py-2' : 'h-9 w-9';
@endphp

<{{ $tag }}
    @if ($tag === 'a') href="{{ $href }}" @else type="{{ $type }}" @endif
    data-ui-action
    aria-label="{{ $label }}"
    title="{{ $label }}"
    {{ $attributes->merge(['class' => 'th-focus inline-flex items-center justify-center gap-2 rounded-lg border text-sm font-semibold transition hover:opacity-90 '.$sizeClass]) }}
    style="{{ $variantStyle }}"
>
    <x-ui.icon :name="$icon" />
    @if ($showLabel)
        <span>{{ $label }}</span>
    @endif
</{{ $tag }}>
