@props([
    'name',
])

@php
    $icons = [
        'add' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'approve' => '<path d="m5 12 4 4L19 6"/>',
        'arrow-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-up' => '<path d="m18 15-6-6-6 6"/>',
        'back' => '<path d="m15 18-6-6 6-6"/>',
        'cancel' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clear' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        'delete' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'filter' => '<path d="M3 5h18"/><path d="M6 12h12"/><path d="M10 19h4"/>',
        'chat' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M8 9h8"/><path d="M8 13h5"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/><path d="M12 7v5l3 2"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'mark-read' => '<path d="M22 12s-4 7-10 7S2 12 2 12s4-7 10-7 10 7 10 7Z"/><path d="m9 12 2 2 4-4"/>',
        'move' => '<path d="M12 2v20"/><path d="m8 6 4-4 4 4"/><path d="m8 18 4 4 4-4"/><path d="M2 12h20"/><path d="m6 8-4 4 4 4"/><path d="m18 8 4 4-4 4"/>',
        'open' => '<path d="M7 17 17 7"/><path d="M7 7h10v10"/>',
        'refresh' => '<path d="M21 12a9 9 0 0 1-15.5 6.2"/><path d="M3 12A9 9 0 0 1 18.5 5.8"/><path d="M18 2v4h-4"/><path d="M6 22v-4h4"/>',
        'reject' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'status' => '<path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h7"/><path d="m16 16 2 2 4-5"/>',
        'unlock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
        'upload' => '<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M5 3h14"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'view' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'warning' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    ];
@endphp

<svg
    data-ui-icon="{{ $name }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.9"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes->merge(['class' => 'h-4 w-4']) }}
>
    {!! $icons[$name] ?? $icons['open'] !!}
</svg>
