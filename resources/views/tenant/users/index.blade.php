<x-app-layout>
    @php($temporaryCredentials = session('temporary_credentials', []))
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Cadastros</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Usuarios</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Usuarios com ramal</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Usuarios ativos recebem ramal. Ao inativar, o numero e liberado. Agentes ficam em uma equipe e podem participar de varias campanhas.</p>
            </div>
            @can('tenant.users.create')
                <div class="flex shrink-0 flex-wrap gap-2">
                    <x-ui.action as="a" :href="route('tenant.users.export')" icon="download" label="Exportar usuarios" show-label />
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'user-bulk-create')"><x-ui.icon name="users" /> Criacao em massa</x-ui.button>
                    <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'user-create')"><x-ui.icon name="add" /> Novo usuario</x-ui.button>
                </div>
            @else
                <x-ui.action as="a" :href="route('tenant.users.export')" icon="download" label="Exportar usuarios" show-label />
            @endcan
        </div>

        <form method="GET" action="{{ route('tenant.users.index') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(240px,1.4fr)_180px_180px_220px_220px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar nome, e-mail ou ramal" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativo</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativo</option>
            </select>
            <select name="user_type" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos tipos</option>
                @foreach (['agent' => 'Agente', 'supervisor' => 'Supervisor', 'company_admin' => 'Administrador'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['user_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="role_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos perfis</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) ($filters['role_id'] ?? 0) === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="team_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todas equipes</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) ($filters['team_id'] ?? 0) === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <x-ui.button type="submit">Filtrar</x-ui.button>
                <a href="{{ route('tenant.users.index') }}" class="th-focus inline-flex items-center justify-center px-4 py-2 text-sm font-semibold" style="border: 1px solid var(--border); background: var(--surface-alt); color: var(--fg); border-radius: var(--radius-md);">Limpar</a>
            </div>
        </form>

        <div class="mt-5 grid gap-3">
            @forelse ($users as $user)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $user->name }}</p>
                                <span class="rounded-full px-2 py-1 text-xs" style="background: {{ $user->status === 'active' ? 'color-mix(in srgb, var(--status-success) 12%, var(--surface))' : 'var(--bg-subtle)' }}; color: {{ $user->status === 'active' ? 'var(--status-success)' : 'var(--fg-muted)' }};">
                                    {{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $user->email }} | Ramal {{ $user->extension }}</p>
                            <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $user->role?->name ?? 'Sem perfil' }} | {{ $user->assignedTeams->pluck('name')->join(', ') ?: 'Sem equipe' }}</p>
                            @if ($user->user_type === 'agent')
                                <p class="mt-1 text-xs" style="color: var(--fg-muted);">Campanhas: {{ $user->assignedCampaigns->pluck('name')->join(', ') ?: 'Nenhuma campanha direcionada' }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'user-view-{{ $user->id }}')" />
                            @can('tenant.users.update')
                                <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'user-edit-{{ $user->id }}')" />
                                @if ($user->status === 'active')
                                    <x-ui.action icon="refresh" label="Regerar ramal" variant="warning" x-data x-on:click="$dispatch('open-modal', 'user-extension-{{ $user->id }}')" />
                                @endif
                            @endcan
                            @can('tenant.users.delete')
                                @if ($user->status === 'active' && $user->id !== auth()->id())
                                    <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'user-delete-{{ $user->id }}')" />
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>

                <x-ui.modal name="user-view-{{ $user->id }}" title="Detalhes do usuario" max-width="lg">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold">Nome</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->name }}</dd></div>
                        <div><dt class="font-semibold">E-mail</dt><dd class="mt-1 break-all" style="color: var(--fg-muted);">{{ $user->email }}</dd></div>
                        <div><dt class="font-semibold">Ramal</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->extension }}</dd></div>
                        <div><dt class="font-semibold">Tipo</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->user_type }}</dd></div>
                        <div><dt class="font-semibold">Perfil</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->role?->name ?? 'Sem perfil' }}</dd></div>
                        <div><dt class="font-semibold">Status</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-semibold">Equipes</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->assignedTeams->pluck('name')->join(', ') ?: 'Sem equipe' }}</dd></div>
                        @if ($user->user_type === 'agent')
                            <div class="sm:col-span-2"><dt class="font-semibold">Campanhas</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->assignedCampaigns->pluck('name')->join(', ') ?: 'Nenhuma campanha direcionada' }}</dd></div>
                        @endif
                    </dl>
                </x-ui.modal>

                @can('tenant.users.update')
                    <x-ui.modal name="user-edit-{{ $user->id }}" title="Editar usuario" :show="$errors->any() && (int) old('_user_id') === $user->id">
                        <form method="POST" action="{{ route('tenant.users.update', $user) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_user_id" value="{{ $user->id }}">
                            @include('tenant.users._form', ['user' => $user, 'roles' => $roles, 'teams' => $teams, 'campaigns' => $campaigns])
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'user-edit-{{ $user->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Salvar usuario</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>

                    <x-ui.modal name="user-extension-{{ $user->id }}" title="Regerar ramal" max-width="md">
                        <form method="POST" action="{{ route('tenant.users.extension.regenerate', $user) }}">
                            @csrf
                            @method('PATCH')
                            <p class="text-sm" style="color: var(--fg-muted);">O ramal atual <strong style="color: var(--fg);">{{ $user->extension }}</strong> sera trocado por outro numero unico e disponivel entre 1000 e 10000.</p>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'user-extension-{{ $user->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Gerar outro ramal</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endcan

                @can('tenant.users.delete')
                    @if ($user->status === 'active' && $user->id !== auth()->id())
                        <x-ui.modal name="user-delete-{{ $user->id }}" title="Inativar usuario" max-width="md">
                            <form method="POST" action="{{ route('tenant.users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')
                                <p class="text-sm" style="color: var(--fg-muted);">{{ $user->name }} perdera o acesso ao sistema, mas permanecera no historico operacional.</p>
                                <div class="mt-5 flex justify-end gap-2">
                                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'user-delete-{{ $user->id }}')">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="danger">Inativar usuario</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endif
                @endcan
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhum usuario cadastrado.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $users->links() }}</div>
    </x-ui.card>

    @can('tenant.users.create')
        <x-ui.modal name="user-create" title="Novo usuario">
            <form method="POST" action="{{ route('tenant.users.store') }}">
                @csrf
                @include('tenant.users._form', ['user' => null, 'roles' => $roles, 'teams' => $teams, 'campaigns' => $campaigns])
                <div class="mt-5 flex justify-end">
                    <x-ui.button type="submit">Criar usuario</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="user-bulk-create" title="Criacao em massa de usuarios" max-width="2xl">
            <form method="POST" action="{{ route('tenant.users.bulk.store') }}" x-data="{
                type: @js(old('user_type', 'agent')),
                quantity: {{ max(1, (int) old('quantity', 1)) }},
                namePrefix: @js(old('name_prefix', 'Agente')),
                emailBase: @js(old('email_base', 'operacao@empresa.com.br')),
                confirm: false,
                generatedEmail(number) {
                    const parts = this.emailBase.trim().toLowerCase().split('@');
                    return parts.length === 2 && parts[0] && parts[1]
                        ? `${parts[0]}+${String(number).padStart(3, '0')}@${parts[1]}`
                        : 'Informe um e-mail base valido';
                }
            }">
                @csrf
                <input type="hidden" name="confirmed" :value="confirm ? '1' : '0'">
                <p class="mb-4 text-sm" style="color: var(--fg-muted);">Informe uma base para os nomes e e-mails. O sistema cria os usuarios enumerados, com ramais disponiveis e senhas temporarias fortes e exclusivas.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Tipo de usuario</span>
                        <select name="user_type" x-model="type" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="agent">Agente</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Quantidade</span>
                        <input type="number" name="quantity" min="1" max="100" x-model.number="quantity" placeholder="Ex.: 10" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Nome base</span>
                        <input name="name_prefix" x-model="namePrefix" placeholder="Ex.: Agente Vendas" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                        <span class="text-xs" style="color: var(--fg-muted);">Serao gerados como Agente Vendas 01, 02, 03...</span>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Perfil</span>
                        <select name="role_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) old('role_id') === $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm md:col-span-2">
                        <span class="font-semibold">E-mail base</span>
                        <input type="email" name="email_base" x-model="emailBase" placeholder="Ex.: vendas@empresa.com.br" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                        <span class="text-xs" style="color: var(--fg-muted);">Exemplo: vendas@empresa.com.br gera vendas+001@empresa.com.br, vendas+002@empresa.com.br...</span>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Status inicial</span>
                        <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 grid gap-4">
                    <div x-show="type === 'agent'" x-cloak>
                        <p class="mb-1 text-sm font-semibold">Equipe</p>
                        <select name="team_ids[]" x-bind:disabled="type !== 'agent'" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Sem equipe definida</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="type === 'supervisor'" x-cloak>
                        <p class="mb-2 text-sm font-semibold">Equipes acompanhadas</p>
                        <x-ui.multi-select name="team_ids[]" :options="$teams" placeholder="Selecionar equipes" disabled-when="type !== 'supervisor'" />
                    </div>
                    <div x-show="type === 'agent'" x-cloak>
                        <p class="mb-2 text-sm font-semibold">Campanhas direcionadas</p>
                        <x-ui.multi-select name="campaign_ids[]" :options="$campaigns" placeholder="Selecionar campanhas" empty-message="Nenhuma campanha disponivel." disabled-when="type !== 'agent'" />
                    </div>
                </div>

                <section x-show="confirm" x-cloak class="mt-5 rounded-lg border p-4" style="border-color: color-mix(in srgb, var(--primary) 35%, var(--border)); background: color-mix(in srgb, var(--primary) 6%, var(--surface));">
                    <p class="font-semibold">Revise antes de criar</p>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">Serão criados <strong x-text="quantity"></strong> usuarios com os seguintes dados:</p>
                    <div class="mt-3 overflow-hidden rounded-md border" style="border-color: var(--border);">
                        <div class="grid grid-cols-[1fr_1.35fr] gap-3 px-3 py-2 text-xs font-semibold" style="background: var(--surface-muted); color: var(--fg-muted);">
                            <span>Nome</span><span>E-mail de acesso</span>
                        </div>
                        <template x-for="number in Math.min(quantity || 0, 10)" :key="number">
                            <div class="grid grid-cols-[1fr_1.35fr] gap-3 border-t px-3 py-2 text-sm" style="border-color: var(--border);">
                                <span x-text="`${namePrefix || 'Usuario'} ${String(number).padStart(2, '0')}`"></span>
                                <span class="break-all" style="color: var(--fg-muted);" x-text="generatedEmail(number)"></span>
                            </div>
                        </template>
                    </div>
                    <p class="mt-2 text-xs" style="color: var(--fg-muted);">As senhas temporarias serao exibidas uma unica vez depois da criacao. Usuarios ativos receberao um ramal livre automaticamente.</p>
                    <span class="mt-2 block text-sm" x-show="quantity > 10" x-text="`... e mais ${quantity - 10} usuario(s) com a mesma regra.`"></span>
                </section>

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'user-bulk-create')">Cancelar</x-ui.button>
                    <x-ui.button type="button" x-show="!confirm" x-on:click="confirm = true">Revisar criacao</x-ui.button>
                    <x-ui.button type="submit" x-show="confirm">Confirmar e criar usuarios</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan

    @if (count($temporaryCredentials) > 0)
        <x-ui.modal name="temporary-user-credentials" title="Credenciais temporarias" :show="true" max-width="2xl">
            <div x-data="{ copied: '', copy(value, email) { navigator.clipboard.writeText(value).then(() => { this.copied = email; setTimeout(() => this.copied = '', 1800); }); } }">
                <p class="text-sm" style="color: var(--fg-muted);">Estas senhas aparecem somente agora. Entregue cada credencial ao respectivo usuario por um canal seguro.</p>
                <div class="mt-4 overflow-x-auto rounded-lg border" style="border-color: var(--border);">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead style="background: var(--surface-muted); color: var(--fg-muted);">
                            <tr><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">Senha temporaria</th><th class="w-28 px-3 py-2">Acao</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($temporaryCredentials as $credential)
                                <tr class="border-t" style="border-color: var(--border);">
                                    <td class="px-3 py-2">{{ $credential['email'] }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $credential['password'] }}</td>
                                    <td class="px-3 py-2">
                                        <x-ui.button type="button" variant="secondary" x-on:click="copy(@js($credential['password']), @js($credential['email']))">
                                            <span x-text="copied === @js($credential['email']) ? 'Copiado' : 'Copiar'"></span>
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5 flex justify-end">
                    <x-ui.button type="button" x-data x-on:click="$dispatch('close-modal', 'temporary-user-credentials')">Concluir</x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</x-app-layout>
