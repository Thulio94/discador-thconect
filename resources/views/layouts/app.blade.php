@php
    $user = Auth::user();
    $theme = $user?->theme_preference ?: 'thconectclaro';
    $canReadNotifications = ! $user?->is_super_admin && Gate::allows('tenant.notifications.read');
    $notifications = $canReadNotifications ? $user->appNotifications()->latest()->limit(8)->get() : collect();
    $unreadCount = $canReadNotifications ? $user->appNotifications()->whereNull('read_at')->count() : 0;
    $notificationCategories = $canReadNotifications ? \App\Support\Notifications\AppNotificationService::preferenceCategories() : [];
    $notificationPreferences = $canReadNotifications ? $user->notificationPreferences()->pluck('enabled', 'category')->all() : [];
    $navItems = $user?->is_super_admin
        ? [
            ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'status', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Empresas', 'href' => route('super-admin.companies.index'), 'icon' => 'users', 'active' => request()->routeIs('super-admin.companies.*')],
            ['label' => 'Status do sistema', 'href' => route('super-admin.system-status.index'), 'icon' => 'status', 'active' => request()->routeIs('super-admin.system-status.*')],
            ['label' => 'Auditoria', 'href' => route('super-admin.audit-logs.index'), 'icon' => 'view', 'active' => request()->routeIs('super-admin.audit-logs.*')],
        ]
        : [
            ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'status', 'active' => request()->routeIs('dashboard'), 'ability' => 'tenant.dashboard.read'],
            ['label' => 'Usuarios', 'href' => route('tenant.users.index'), 'icon' => 'users', 'active' => request()->routeIs('tenant.users.*'), 'ability' => 'tenant.users.read'],
            ['label' => 'Equipes', 'href' => route('tenant.teams.index'), 'icon' => 'view', 'active' => request()->routeIs('tenant.teams.*'), 'ability' => 'tenant.teams.read'],
            ['label' => 'Perfis', 'href' => route('tenant.roles.index'), 'icon' => 'lock', 'active' => request()->routeIs('tenant.roles.*'), 'ability' => 'tenant.roles.read'],
            ['label' => 'Listas', 'href' => route('tenant.lists.index'), 'icon' => 'add', 'active' => request()->routeIs('tenant.lists.*'), 'ability' => 'tenant.lists.read'],
            ['label' => 'Campanhas', 'href' => route('tenant.campaigns.index'), 'icon' => 'chat', 'active' => request()->routeIs('tenant.campaigns.*'), 'ability' => 'tenant.campaigns.read'],
            ['label' => 'Agente', 'href' => route('tenant.agent.index'), 'icon' => 'users', 'active' => request()->routeIs('tenant.agent.*'), 'ability' => 'tenant.agent.access'],
            ['label' => 'Supervisao', 'href' => route('tenant.supervision.index'), 'icon' => 'view', 'active' => request()->routeIs('tenant.supervision.*'), 'ability' => 'tenant.supervision.read'],
            ['label' => 'Relatorios', 'href' => route('tenant.reports.index'), 'icon' => 'download', 'active' => request()->routeIs('tenant.reports.*'), 'ability' => 'tenant.reports.read'],
            ['label' => 'Telefonia', 'href' => route('tenant.telephony.index'), 'icon' => 'chat', 'active' => request()->routeIs('tenant.telephony.*'), 'ability' => 'tenant.telephony.read'],
            ['label' => 'Configuracoes', 'href' => route('tenant.settings.index'), 'icon' => 'lock', 'active' => request()->routeIs('tenant.settings.*'), 'ability' => 'tenant.settings.read'],
            ['label' => 'Auditoria', 'href' => route('tenant.audit-logs.index'), 'icon' => 'view', 'active' => request()->routeIs('tenant.audit-logs.*'), 'ability' => 'tenant.audit.read'],
            ['label' => 'Integracoes', 'href' => route('tenant.integrations.index'), 'icon' => 'open', 'active' => request()->routeIs('tenant.integrations.*'), 'ability' => 'tenant.integrations.read', 'soon' => true],
        ];
    $navItems = collect($navItems)
        ->filter(fn (array $item): bool => ! isset($item['ability']) || Gate::allows($item['ability']))
        ->values()
        ->all();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Discador THCONECT') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="background: var(--bg); color: var(--fg);">
        <div class="min-h-screen lg:grid {{ $canReadNotifications ? 'lg:grid-cols-[272px_minmax(0,1fr)_320px]' : 'lg:grid-cols-[272px_minmax(0,1fr)]' }}">
            <aside class="border-r px-4 py-5" style="background: var(--surface); border-color: var(--border);">
                <div class="flex items-center gap-3 px-2">
                    <div class="grid h-10 w-10 place-items-center rounded-lg font-bold" style="background: var(--primary); color: #fff;">
                        TH
                    </div>
                    <div>
                        <p class="text-sm font-bold">Discador THCONECT</p>
                        <p class="text-xs" style="color: var(--fg-muted);">Painel SaaS</p>
                    </div>
                </div>

                <nav class="mt-8 grid gap-1">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ $item['href'] }}"
                            class="th-focus flex min-h-11 items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition"
                            style="{{ $item['active'] ? 'background: var(--primary-soft); color: var(--fg);' : 'color: var(--fg-muted);' }}"
                        >
                            <span class="flex items-center gap-3">
                                <x-ui.icon :name="$item['icon']" />
                                {{ $item['label'] }}
                            </span>
                            @if ($item['soon'] ?? false)
                                <span class="rounded-full px-2 py-0.5 text-[10px] uppercase" style="background: var(--bg-subtle); color: var(--fg-muted);">
                                    Em breve
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </aside>

            <main class="min-w-0 px-4 py-5 sm:px-6 lg:px-8">
                @isset($header)
                    <header class="mb-6">
                        {{ $header }}
                    </header>
                @endisset

                {{ $slot }}
            </main>

            @if ($canReadNotifications)
                <x-ui.notification-panel
                    :notifications="$notifications"
                    :unread-count="$unreadCount"
                    :categories="$notificationCategories"
                    :preferences="$notificationPreferences"
                />
            @endif
        </div>

        <x-ui.toast-stack />

        @if (session('status'))
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            title: 'Tudo certo',
                            message: @js(session('status')),
                            severity: 'success',
                        },
                    }));
                });
            </script>
        @endif
    </body>
</html>
