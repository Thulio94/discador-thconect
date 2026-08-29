@php
    $user = Auth::user();
    $theme = $user?->theme_preference ?: 'thconectclaro';
    $godMode = app(\App\Support\GodMode\GodModeContext::class);
    $isGodMode = $godMode->isActiveFor($user);
    $godModeCompany = $isGodMode ? $godMode->company() : null;
    $ownerBillingNotice = ! $isGodMode && $user?->user_type === 'company_admin'
        ? $user->company?->license?->billingNotice(5)
        : null;
    $navItems = $user?->is_super_admin && ! $isGodMode
        ? [
            ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'pulse', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Empresas', 'href' => route('super-admin.companies.index'), 'icon' => 'building', 'active' => request()->routeIs('super-admin.companies.*')],
            ['label' => 'Rotas de telefonia', 'href' => route('super-admin.telephony-routes.index'), 'icon' => 'phone', 'active' => request()->routeIs('super-admin.telephony-routes.*')],
            ['label' => 'Capacidade', 'href' => route('super-admin.capacity.index'), 'icon' => 'status', 'active' => request()->routeIs('super-admin.capacity.*')],
            ['label' => 'Status do sistema', 'href' => route('super-admin.system-status.index'), 'icon' => 'status', 'active' => request()->routeIs('super-admin.system-status.*')],
            ['label' => 'Auditoria', 'href' => route('super-admin.audit-logs.index'), 'icon' => 'history', 'active' => request()->routeIs('super-admin.audit-logs.*')],
        ]
        : [
            ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'pulse', 'active' => request()->routeIs('dashboard'), 'ability' => 'tenant.dashboard.read'],
            ['label' => 'Usuarios', 'href' => route('tenant.users.index'), 'icon' => 'users', 'active' => request()->routeIs('tenant.users.*'), 'ability' => 'tenant.users.read'],
            ['label' => 'Equipes', 'href' => route('tenant.teams.index'), 'icon' => 'team', 'active' => request()->routeIs('tenant.teams.*'), 'ability' => 'tenant.teams.read'],
            ['label' => 'Perfis', 'href' => route('tenant.roles.index'), 'icon' => 'lock', 'active' => request()->routeIs('tenant.roles.*'), 'ability' => 'tenant.roles.read'],
            ['label' => 'Listas', 'href' => route('tenant.lists.index'), 'icon' => 'list', 'active' => request()->routeIs('tenant.lists.*'), 'ability' => 'tenant.lists.read'],
            ['label' => 'Campanhas', 'href' => route('tenant.campaigns.index'), 'icon' => 'campaign', 'active' => request()->routeIs('tenant.campaigns.*'), 'ability' => 'tenant.campaigns.read'],
            ['label' => 'Agente', 'href' => route('tenant.agent.index'), 'icon' => 'phone', 'active' => request()->routeIs('tenant.agent.*'), 'ability' => 'tenant.agent.access'],
            ['label' => 'Supervisao', 'href' => route('tenant.supervision.index'), 'icon' => 'view', 'active' => request()->routeIs('tenant.supervision.*'), 'ability' => 'tenant.supervision.read'],
            ['label' => 'Relatorios', 'href' => route('tenant.reports.index'), 'icon' => 'report', 'active' => request()->routeIs('tenant.reports.*'), 'ability' => 'tenant.reports.read'],
            ['label' => 'Telefonia', 'href' => route('tenant.telephony.index'), 'icon' => 'phone', 'active' => request()->routeIs('tenant.telephony.*'), 'ability' => 'tenant.telephony.read'],
            ['label' => 'Configuracoes', 'href' => route('tenant.settings.index'), 'icon' => 'settings', 'active' => request()->routeIs('tenant.settings.*'), 'ability' => 'tenant.settings.read'],
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
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">

        <title>{{ config('app.name', 'Discador THCONECT') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="background: var(--bg); color: var(--fg);">
        <div
            x-data="{
                navOpen: false,
                sidebarCollapsed: false,
                viewportWidth: window.innerWidth,
                init() {
                    this.sidebarCollapsed = window.localStorage.getItem('thconect:sidebar-collapsed') === 'true';
                    window.addEventListener('resize', () => this.viewportWidth = window.innerWidth);
                },
                toggleSidebar() {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    window.localStorage.setItem('thconect:sidebar-collapsed', String(this.sidebarCollapsed));
                },
                isSidebarCollapsed() {
                    return this.sidebarCollapsed && this.viewportWidth >= 1024;
                },
            }"
            x-bind:style="sidebarCollapsed ? '--th-sidebar-desktop-width: 5.5rem' : '--th-sidebar-desktop-width: 16rem'"
            class="th-app-shell min-h-screen"
        >
            <div x-show="navOpen" x-cloak class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" x-on:click="navOpen = false"></div>

            <aside
                data-layout-sidebar
                class="th-sidebar fixed inset-y-0 left-0 z-50 flex -translate-x-full flex-col border-r px-3 py-4 transition-[transform,width] duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                x-bind:class="navOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="flex items-center justify-between gap-3 px-2 pb-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-white p-1.5 shadow-sm">
                            <x-application-logo class="h-full w-full" />
                        </div>
                        <div x-show="!isSidebarCollapsed()" x-transition.opacity class="min-w-0">
                            <p class="truncate text-xs font-bold text-white">Discador THCONECT</p>
                            <p class="text-[11px] font-medium uppercase" style="color: var(--sidebar-muted);">Central de operacao</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="th-focus hidden h-9 w-9 shrink-0 place-items-center rounded-lg lg:grid"
                        style="color: var(--sidebar-muted);"
                        x-on:click="toggleSidebar()"
                        x-bind:aria-label="isSidebarCollapsed() ? 'Expandir menu' : 'Recolher menu'"
                        x-bind:title="isSidebarCollapsed() ? 'Expandir menu' : 'Recolher menu'"
                    >
                        <x-ui.icon name="sidebar-collapse" x-show="!isSidebarCollapsed()" />
                        <x-ui.icon name="sidebar-expand" x-show="isSidebarCollapsed()" x-cloak />
                    </button>
                    <button type="button" class="th-focus grid h-9 w-9 place-items-center rounded-lg lg:hidden" style="color: var(--sidebar-muted);" x-on:click="navOpen = false" aria-label="Fechar menu">
                        <x-ui.icon name="cancel" />
                    </button>
                </div>

                <div class="mx-2 border-t" style="border-color: var(--sidebar-border);"></div>

                <nav class="mt-4 grid flex-1 content-start gap-1 overflow-y-auto pr-1">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ $item['href'] }}"
                            data-active="{{ $item['active'] ? 'true' : 'false' }}"
                            class="th-nav-link th-focus flex min-h-10 items-center rounded-lg py-2 text-[13px] font-semibold transition"
                            x-bind:class="isSidebarCollapsed() ? 'justify-center px-2' : 'justify-between px-3'"
                            x-bind:title="isSidebarCollapsed() ? @js($item['label']) : null"
                            x-bind:aria-label="isSidebarCollapsed() ? @js($item['label']) : null"
                        >
                            <span class="flex items-center" x-bind:class="isSidebarCollapsed() ? 'justify-center' : 'gap-3'">
                                <x-ui.icon :name="$item['icon']" />
                                <span x-show="!isSidebarCollapsed()" x-transition.opacity>{{ $item['label'] }}</span>
                            </span>
                            @if ($item['soon'] ?? false)
                                <span x-show="!isSidebarCollapsed()" x-transition.opacity class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase" style="background: var(--sidebar-bg-hover); color: var(--sidebar-muted);">
                                    Em breve
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="mt-4 border-t pt-4" style="border-color: var(--sidebar-border);">
                    <a
                        href="{{ route('profile.edit') }}"
                        class="th-nav-link th-focus flex items-center rounded-lg py-2 transition"
                        x-bind:class="isSidebarCollapsed() ? 'justify-center px-2' : 'gap-3 px-3'"
                        x-bind:title="isSidebarCollapsed() ? @js($user?->name ?? 'Meu perfil') : null"
                    >
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-xs font-bold text-white" style="background: var(--primary);">{{ strtoupper(substr($user?->name ?? 'U', 0, 2)) }}</span>
                        <span x-show="!isSidebarCollapsed()" x-transition.opacity class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-semibold text-white">{{ $user?->name }}</span>
                            <span class="block truncate text-[10px]" style="color: var(--sidebar-muted);">Ramal {{ $user?->extension ?? '-' }}</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button
                            type="submit"
                            class="th-nav-link th-focus flex min-h-10 w-full items-center rounded-lg py-2 text-left text-xs font-semibold transition"
                            x-bind:class="isSidebarCollapsed() ? 'justify-center px-2' : 'gap-3 px-3'"
                            x-bind:title="isSidebarCollapsed() ? 'Sair do sistema' : null"
                            x-bind:aria-label="isSidebarCollapsed() ? 'Sair do sistema' : null"
                        >
                            <x-ui.icon name="logout" />
                            <span x-show="!isSidebarCollapsed()" x-transition.opacity>Sair do sistema</span>
                        </button>
                    </form>
                </div>
            </aside>

            <main class="th-main min-w-0 px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
                @if ($isGodMode && $godModeCompany)
                    <div class="mb-5 flex flex-col gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center sm:justify-between" style="border-color: color-mix(in srgb, var(--accent-violet) 48%, var(--border)); background: color-mix(in srgb, var(--accent-violet) 10%, var(--surface));">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-white" style="background: var(--accent-violet);"><x-ui.icon name="view" /></span>
                            <div>
                                <p class="text-sm font-bold">Modo Deus ativo</p>
                                <p class="text-xs" style="color: var(--fg-muted);">Acompanhando {{ $godModeCompany->name }}. Acoes de telefonia permanecem protegidas.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('super-admin.god-mode.leave') }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="secondary">Sair da empresa</x-ui.button>
                        </form>
                    </div>
                @endif

                @if ($ownerBillingNotice && $ownerBillingNotice['is_due_soon'])
                    <div class="mb-5 flex items-start gap-3 rounded-lg border px-4 py-3" style="border-color: color-mix(in srgb, var(--status-warning) 45%, var(--border)); background: color-mix(in srgb, var(--status-warning) 10%, var(--surface));">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" style="background: color-mix(in srgb, var(--status-warning) 20%, var(--surface)); color: var(--status-warning);"><x-ui.icon name="status" /></span>
                        <div>
                            <p class="text-sm font-bold">Pagamento proximo</p>
                            <p class="text-xs" style="color: var(--fg-muted);">O pagamento da sua empresa esta previsto para {{ $ownerBillingNotice['due_at']->format('d/m/Y') }}.</p>
                        </div>
                    </div>
                @endif

                <div class="mb-5 flex items-center justify-between border-b pb-3 lg:hidden" style="border-color: var(--border);">
                    <button type="button" class="th-focus grid h-10 w-10 place-items-center rounded-lg border" style="border-color: var(--border); background: var(--surface); color: var(--fg);" x-on:click="navOpen = true" aria-label="Abrir menu">
                        <x-ui.icon name="list" />
                    </button>
                    <span class="text-sm font-bold">Discador THCONECT</span>
                </div>

                @isset($header)
                    <header class="th-page-header mb-6">
                        {{ $header }}
                    </header>
                @endisset

                {{ $slot }}
            </main>
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
