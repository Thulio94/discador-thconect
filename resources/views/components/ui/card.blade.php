@props([
    'title' => null,
])

<section {{ $attributes->merge(['class' => 'th-card p-5']) }}>
    @if ($title)
        <header class="mb-4">
            <h2 class="text-lg font-semibold" style="color: var(--fg);">{{ $title }}</h2>
        </header>
    @endif

    {{ $slot }}
</section>
