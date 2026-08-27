@props([
    'name',
    'title' => null,
    'maxWidth' => '4xl',
    'show' => false,
])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-xl',
        'xl' => 'sm:max-w-2xl',
        '2xl' => 'sm:max-w-3xl',
        '3xl' => 'sm:max-w-4xl',
        '4xl' => 'sm:max-w-5xl',
        '5xl' => 'sm:max-w-6xl',
        '6xl' => 'sm:max-w-7xl',
    ][$maxWidth] ?? 'sm:max-w-5xl';
@endphp

<div
    x-data="modalState(@js($name), {{ $show ? 'true' : 'false' }})"
    x-on:open-modal.window="openModal($event.detail)"
    x-on:close-modal.window="closeModal($event.detail)"
    x-on:keydown.escape.window="close()"
    x-show="show"
    x-cloak
    x-bind:data-modal-open="show ? 'true' : 'false'"
    data-ui-modal
    class="fixed inset-0 z-[80] overflow-y-auto px-4 py-6 sm:px-6"
    x-on:click.self="close()"
>
    <div
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 z-0"
        style="background: color-mix(in srgb, #020617 48%, transparent);"
        x-on:click="close()"
    ></div>

    <div
        x-show="show"
        x-transition
        x-on:click.stop
        class="relative z-10 mx-auto my-auto w-full transform overflow-y-auto rounded-lg border shadow-xl {{ $maxWidthClass }} max-h-[calc(100dvh-3rem)]"
        style="background: var(--surface); border-color: var(--border); color: var(--fg);"
    >
        @if ($title)
            <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b px-6 py-4" style="border-color: var(--border); background: var(--surface);">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button
                    type="button"
                    class="th-focus inline-flex h-9 w-9 items-center justify-center rounded-lg border"
                    style="border-color: var(--border); color: var(--fg-muted);"
                    x-on:click="close()"
                    aria-label="Fechar"
                >
                    <x-ui.icon name="cancel" />
                </button>
            </header>
        @endif

        <div class="p-6 lg:p-7">
            {{ $slot }}
        </div>
    </div>
</div>
