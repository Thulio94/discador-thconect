<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Super admin</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Empresas</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Licencas em uso">
            @include('partials.license-usage-meter', ['label' => 'Usuarios disponiveis', 'metric' => $licenseOverview['available_user_slots']])
        </x-ui.card>
        <x-ui.card title="Agentes disponiveis">
            <p class="text-3xl font-bold">{{ $licenseOverview['available_agents'] }}</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Agentes em status disponivel para receber chamadas.</p>
        </x-ui.card>
        <x-ui.card title="Chamadas contratadas">
            @include('partials.license-usage-meter', ['label' => 'Chamadas ativas', 'metric' => $licenseOverview['active_calls']])
        </x-ui.card>
    </div>

    <x-ui.card class="mt-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Contratos SaaS</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Uso contratado versus uso atual por empresa.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.action
                    as="a"
                    :href="route('super-admin.companies.index', $showInactive ? [] : ['show_inactive' => 1])"
                    :icon="$showInactive ? 'cancel' : 'view'"
                    :label="$showInactive ? 'Ocultar inativas' : 'Mostrar inativas'.($inactiveCompaniesCount ? ' ('.$inactiveCompaniesCount.')' : '')"
                    :show-label="true"
                />
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'company-create')">Nova empresa</x-ui.button>
            </div>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($companies as $company)
                @php($usage = $company->license_usage)
                @php($billing = $company->license?->billingNotice())
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $company->name }}</p>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: {{ $usage['status'] === 'exceeded' ? 'color-mix(in srgb, var(--status-danger) 12%, var(--surface))' : ($usage['status'] === 'warning' ? 'color-mix(in srgb, var(--status-warning) 14%, var(--surface))' : 'var(--primary-soft)') }}; color: var(--fg);">
                                    {{ $usage['status'] === 'exceeded' ? 'Limite excedido' : ($usage['status'] === 'warning' ? 'Atencao' : 'Normal') }}
                                </span>
                                @if ($company->status === 'blocked')
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: color-mix(in srgb, var(--status-danger) 14%, var(--surface)); color: var(--status-danger);">Empresa bloqueada</span>
                                @elseif ($company->status === 'inactive')
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: var(--surface-muted); color: var(--fg-muted);">Empresa inativa</span>
                                @endif
                                @if ($billing)
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: {{ $billing['is_due_soon'] ? 'color-mix(in srgb, var(--status-warning) 16%, var(--surface))' : 'var(--surface-muted)' }}; color: var(--fg);">
                                        {{ $billing['is_due_soon'] ? 'Cobranca: ' : 'Proximo pagamento: ' }}{{ $billing['due_at']->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                {{ $usage['active_calls']['current'] }} ativa de {{ $usage['active_calls']['limit'] ?? 'sem teto' }}
                                <span aria-hidden="true"> · </span>
                                {{ $usage['available_user_slots']['current'] }} usuario{{ $usage['available_user_slots']['current'] === 1 ? '' : 's' }} disponivel{{ $usage['available_user_slots']['current'] === 1 ? '' : 's' }} de {{ $usage['available_user_slots']['limit'] ?? 'sem teto' }}
                                <span aria-hidden="true"> · </span>
                                {{ $usage['calls_per_available_agent'] }} por operador disponivel
                                <span aria-hidden="true"> · </span>
                                capacidade efetiva {{ $usage['effective_call_limit'] }}
                            </p>
                            <p class="mt-1 text-xs" style="color: var(--fg-muted);">Rotas liberadas: {{ $company->telephonyRoutes->pluck('name')->join(', ') ?: 'Nenhuma' }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'company-view-{{ $company->id }}')" />
                            <x-ui.action icon="open" label="Entrar no Modo Deus" x-data x-on:click="$dispatch('open-modal', 'company-god-mode-{{ $company->id }}')" />
                            <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'company-edit-{{ $company->id }}')" />
                            @if ($company->status === 'active')
                                <x-ui.action icon="lock" label="Bloquear empresa" variant="warning" x-data x-on:click="$dispatch('open-modal', 'company-block-{{ $company->id }}')" />
                                <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'company-delete-{{ $company->id }}')" />
                            @elseif ($company->status === 'blocked')
                                <x-ui.action icon="unlock" label="Desbloquear empresa" variant="warning" x-data x-on:click="$dispatch('open-modal', 'company-unblock-{{ $company->id }}')" />
                                <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'company-delete-{{ $company->id }}')" />
                            @else
                                <x-ui.action icon="unlock" label="Reativar empresa" variant="primary" x-data x-on:click="$dispatch('open-modal', 'company-activate-{{ $company->id }}')" />
                            @endif
                        </div>
                    </div>

                    <x-ui.modal name="company-view-{{ $company->id }}" title="Detalhes da empresa" max-width="lg">
                        <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div><dt class="font-semibold">Empresa</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->name }}</dd></div>
                            <div><dt class="font-semibold">Documento</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->document ?: 'Nao informado' }}</dd></div>
                            <div><dt class="font-semibold">Status</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ ['active' => 'Ativa', 'blocked' => 'Bloqueada', 'inactive' => 'Inativa'][$company->status] }}</dd></div>
                            <div><dt class="font-semibold">Vencimento mensal</dt><dd class="mt-1" style="color: var(--fg-muted);">Todo dia {{ $company->license?->billing_due_day ?? '-' }}. Proximo: {{ $billing ? $billing['due_at']->format('d/m/Y') : '-' }}</dd></div>
                            <div><dt class="font-semibold">Usuarios disponiveis</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $usage['available_user_slots']['current'] }} / {{ $usage['available_user_slots']['limit'] ?? 'sem teto' }}</dd></div>
                            <div><dt class="font-semibold">Chamadas simultaneas</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $usage['active_calls']['current'] }} / {{ $usage['active_calls']['limit'] ?? 'sem teto' }}</dd></div>
                            <div><dt class="font-semibold">Rotas liberadas</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->telephonyRoutes->pluck('name')->join(', ') ?: 'Nenhuma' }}</dd></div>
                        </dl>
                    </x-ui.modal>

                    <x-ui.modal name="company-god-mode-{{ $company->id }}" title="Entrar no Modo Deus" max-width="lg">
                        <p class="text-sm" style="color: var(--fg-muted);">Voce acompanhara <span class="font-semibold" style="color: var(--fg);">{{ $company->name }}</span> com acesso administrativo aos modulos da empresa. Nenhum usuario sera criado e esta sessao nao consome capacidade.</p>
                        <p class="mt-3 text-sm" style="color: var(--fg-muted);">Acoes que originam ou alteram chamadas permanecem bloqueadas neste modo.</p>
                        <form method="POST" action="{{ route('super-admin.companies.god-mode.enter', $company) }}" class="mt-5 flex justify-end gap-2">
                            @csrf
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'company-god-mode-{{ $company->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Entrar na empresa</x-ui.button>
                        </form>
                    </x-ui.modal>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        @include('partials.license-usage-meter', ['label' => 'Licencas em uso', 'metric' => $usage['available_user_slots']])
                        <div class="grid gap-2 text-sm"><span class="font-semibold">Agentes disponiveis</span><span class="text-2xl font-bold">{{ $usage['available_agents'] }}</span><span class="text-xs" style="color: var(--fg-muted);">Em status disponivel para receber chamadas.</span></div>
                        @include('partials.license-usage-meter', ['label' => 'Chamadas simultaneas', 'metric' => $usage['active_calls']])
                    </div>

                    <x-ui.modal name="company-edit-{{ $company->id }}" title="Editar empresa">
                        <form method="POST" action="{{ route('super-admin.companies.update', $company) }}">
                            @csrf
                            @method('PUT')
                            @include('super-admin.companies._form', ['company' => $company])
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="submit">Salvar</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>

                    @if ($company->status !== 'inactive')
                    <x-ui.modal name="company-block-{{ $company->id }}" title="Bloquear empresa">
                        <form method="POST" action="{{ route('super-admin.companies.block', $company) }}">
                            @csrf
                            @method('PATCH')
                            <p class="text-sm" style="color: var(--fg-muted);">Os usuarios desta empresa perderao o acesso a operacao ate que ela seja desbloqueada.</p>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'company-block-{{ $company->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="danger">Bloquear empresa</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>

                    <x-ui.modal name="company-unblock-{{ $company->id }}" title="Desbloquear empresa">
                        <form method="POST" action="{{ route('super-admin.companies.unblock', $company) }}">
                            @csrf
                            @method('PATCH')
                            <p class="text-sm" style="color: var(--fg-muted);">A empresa voltara a permitir acesso aos usuarios ativos.</p>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'company-unblock-{{ $company->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Desbloquear empresa</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>

                    <x-ui.modal name="company-delete-{{ $company->id }}" title="Inativar empresa">
                        <form method="POST" action="{{ route('super-admin.companies.destroy', $company) }}">
                            @csrf
                            @method('DELETE')
                            <p class="text-sm" style="color: var(--fg-muted);">A empresa sera marcada como inativa.</p>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                    @endif

                    @if ($company->status === 'inactive')
                    <x-ui.modal name="company-activate-{{ $company->id }}" title="Reativar empresa">
                        <form method="POST" action="{{ route('super-admin.companies.activate', $company) }}">
                            @csrf
                            @method('PATCH')
                            <p class="text-sm" style="color: var(--fg-muted);">A empresa voltara para a lista principal e os usuarios poderao acessar a operacao.</p>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'company-activate-{{ $company->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Reativar empresa</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                    @endif
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma empresa cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="company-create" title="Nova empresa">
        <form method="POST" action="{{ route('super-admin.companies.store') }}">
            @csrf
            @include('super-admin.companies._form', ['company' => null])
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="submit">Criar empresa</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
