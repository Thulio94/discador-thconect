@props([
    'notifications' => collect(),
    'unreadCount' => 0,
    'categories' => [],
    'preferences' => [],
])

<aside
    id="notification-panel"
    data-unread-count="{{ $unreadCount }}"
    x-data="pollingRegion({
        url: @js(route('tenant.notifications.poll'), JSON_UNESCAPED_SLASHES),
        selectors: ['#notification-panel'],
        intervalSeconds: 10,
    })"
    x-init="start()"
    x-on:visibilitychange.document="handleVisibilityChange()"
    class="hidden border-l p-4 lg:grid lg:w-80"
    style="background: color-mix(in srgb, var(--surface) 94%, transparent); border-color: var(--border);"
>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Central</p>
            <h2 class="text-sm font-semibold" style="color: var(--fg);">Notificacoes</h2>
        </div>
        <div class="flex items-center gap-2">
            @can('tenant.notifications.update')
                <button
                    type="button"
                    class="th-focus inline-flex h-9 w-9 items-center justify-center rounded-lg border"
                    style="border-color: var(--border); color: var(--fg-muted);"
                    x-on:click="$dispatch('open-modal', 'notification-preferences')"
                    aria-label="Preferencias de notificacao"
                    title="Preferencias de notificacao"
                >
                    <x-ui.icon name="lock" />
                </button>
            @endcan
            <span class="rounded-full px-2 py-1 text-xs" style="background: var(--primary-soft); color: var(--fg);">
                {{ $unreadCount }}
            </span>
        </div>
    </div>

    <div class="mt-4 grid content-start gap-2">
        @forelse ($notifications as $notification)
            <a
                href="{{ $notification->action_url ?: '#' }}"
                data-notification-state="{{ $notification->read_at ? 'read' : 'unread' }}"
                class="rounded-xl border p-3 text-sm"
                style="border-color: var(--border); background: {{ $notification->read_at ? 'var(--bg-subtle)' : 'var(--primary-soft)' }}; color: var(--fg);"
            >
                <span class="block font-semibold">{{ $notification->title }}</span>
                <span class="mt-1 block text-xs" style="color: var(--fg-muted);">{{ $notification->message }}</span>
            </a>
        @empty
            <p class="rounded-xl border p-3 text-sm" style="border-color: var(--border); color: var(--fg-muted);">
                Nenhuma notificacao.
            </p>
        @endforelse
    </div>

    @can('tenant.notifications.update')
        <x-ui.modal name="notification-preferences" title="Preferencias de notificacao" max-width="lg">
            <form method="POST" action="{{ route('tenant.notifications.preferences.update') }}" class="grid gap-4">
                @csrf
                @method('PATCH')

                <div class="grid gap-3">
                    @foreach ($categories as $category => $label)
                        @php
                            $enabled = $preferences[$category] ?? true;
                        @endphp
                        <label class="flex items-start gap-3 rounded-lg border p-3 text-sm" style="border-color: var(--border); background: var(--bg-subtle);">
                            <input
                                type="checkbox"
                                name="categories[]"
                                value="{{ $category }}"
                                @checked($enabled)
                                class="mt-1 h-4 w-4 rounded border"
                                style="accent-color: var(--primary);"
                            >
                            <span>
                                <span class="block font-semibold" style="color: var(--fg);">{{ $label }}</span>
                                <span class="mt-1 block text-xs" style="color: var(--fg-muted);">
                                    Receber notificacoes desta categoria no painel lateral.
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'notification-preferences')">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Salvar preferencias</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</aside>
