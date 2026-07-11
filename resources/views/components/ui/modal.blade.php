@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl',
    'show' => false,
])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
    ][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div
    x-data="{ show: {{ $show ? 'true' : 'false' }} }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail === '{{ $name }}' ? show = false : null"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[80] overflow-y-auto px-4 py-6 sm:px-0"
>
    <div
        x-show="show"
        x-transition.opacity
        class="fixed inset-0"
        style="background: color-mix(in srgb, #020617 48%, transparent);"
        x-on:click="show = false"
    ></div>

    <div
        x-show="show"
        x-transition
        class="relative mx-auto mb-6 w-full transform overflow-hidden rounded-lg border shadow-xl {{ $maxWidthClass }}"
        style="background: var(--surface); border-color: var(--border); color: var(--fg);"
    >
        @if ($title)
            <header class="flex items-center justify-between gap-4 border-b px-5 py-4" style="border-color: var(--border);">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button
                    type="button"
                    class="th-focus inline-flex h-9 w-9 items-center justify-center rounded-lg border"
                    style="border-color: var(--border); color: var(--fg-muted);"
                    x-on:click="show = false"
                    aria-label="Fechar"
                >
                    <x-ui.icon name="cancel" />
                </button>
            </header>
        @endif

        <div class="p-5">
            {{ $slot }}
        </div>
    </div>
</div>
