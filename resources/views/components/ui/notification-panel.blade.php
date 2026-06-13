@props(['notifications' => collect(), 'unreadCount' => 0])

<aside
    id="notification-panel"
    data-unread-count="{{ $unreadCount }}"
    class="hidden border-l p-4 lg:grid lg:w-80"
    style="background: color-mix(in srgb, var(--surface) 94%, transparent); border-color: var(--border);"
>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Central</p>
            <h2 class="text-sm font-semibold" style="color: var(--fg);">Notificacoes</h2>
        </div>
        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--primary-soft); color: var(--fg);">
            {{ $unreadCount }}
        </span>
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
</aside>
