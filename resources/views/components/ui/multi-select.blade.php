@props([
    'name',
    'options' => collect(),
    'selected' => [],
    'placeholder' => 'Selecionar itens',
    'emptyMessage' => 'Nenhum item disponivel.',
    'disabledWhen' => null,
])

@php
    $selectedIds = collect($selected)->map(fn ($id) => (int) $id)->values()->all();
    $items = collect($options)->map(fn ($option) => [
        'id' => (int) data_get($option, 'id'),
        'label' => (string) data_get($option, 'name'),
    ])->values()->all();
@endphp

<div
    x-data="{
        open: false,
        dropUp: false,
        query: '',
        selected: @js($selectedIds),
        options: @js($items),
        label(id) { return this.options.find(option => option.id === id)?.label ?? ''; },
        toggle(id) { this.selected = this.selected.includes(id) ? this.selected.filter(value => value !== id) : [...this.selected, id]; },
        matches(option) { return option.label.toLowerCase().includes(this.query.toLowerCase()); },
        toggleMenu() {
            const trigger = this.$refs.trigger.getBoundingClientRect();
            this.dropUp = window.innerHeight - trigger.bottom < 290 && trigger.top > 290;
            this.open = !this.open;
        },
    }"
    class="relative"
    x-on:click.outside="open = false"
>
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="{{ $name }}" :value="id" @if ($disabledWhen) x-bind:disabled="{{ $disabledWhen }}" @endif>
    </template>

    <button
        type="button"
        x-ref="trigger"
        class="th-focus flex min-h-11 w-full flex-wrap items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm"
        style="border-color: var(--border); background: var(--surface); color: var(--fg);"
        x-on:click="toggleMenu()"
        x-bind:aria-expanded="open"
        @if ($disabledWhen) x-bind:disabled="{{ $disabledWhen }}" @endif
    >
        <template x-if="selected.length === 0">
            <span style="color: var(--fg-muted);">{{ $placeholder }}</span>
        </template>
        <template x-for="id in selected" :key="`tag-${id}`">
            <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold" style="background: color-mix(in srgb, var(--primary) 12%, var(--surface)); color: var(--primary);">
                <span x-text="label(id)"></span>
                <span class="cursor-pointer text-base leading-none" x-on:click.stop="toggle(id)" aria-label="Remover">&times;</span>
            </span>
        </template>
        <x-ui.icon name="arrow-down" class="ml-auto shrink-0" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute z-[100] w-full overflow-hidden rounded-lg border shadow-xl"
        x-bind:class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
        style="border-color: var(--border); background: var(--surface);"
    >
        <div class="border-b p-2" style="border-color: var(--border);">
            <input x-model="query" type="search" placeholder="Pesquisar" class="w-full rounded-md border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg);">
        </div>
        <div class="max-h-56 overflow-y-auto py-1">
            <template x-for="option in options.filter(option => matches(option))" :key="option.id">
                <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm" x-on:click="toggle(option.id)">
                    <span class="flex h-4 w-4 items-center justify-center rounded border" :style="selected.includes(option.id) ? 'border-color: var(--primary); background: var(--primary); color: #fff;' : 'border-color: var(--border);'">
                        <span x-show="selected.includes(option.id)">&#10003;</span>
                    </span>
                    <span x-text="option.label"></span>
                </button>
            </template>
            <p x-show="options.filter(option => matches(option)).length === 0" class="px-3 py-3 text-sm" style="color: var(--fg-muted);">{{ $emptyMessage }}</p>
        </div>
    </div>
</div>
