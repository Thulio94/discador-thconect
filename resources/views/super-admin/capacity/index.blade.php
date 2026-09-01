<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-semibold uppercase" style="color:var(--fg-muted);">Super admin</p><h1 class="text-2xl font-bold">Capacidade e protecao</h1></div></x-slot>

    @php($stateColor = ['normal'=>'var(--status-success)','warning'=>'var(--status-warning)','critical'=>'var(--status-danger)','blocked'=>'var(--status-danger)'][$state])
    <section class="border p-5" style="border-color:{{ $stateColor }};background:color-mix(in srgb, {{ $stateColor }} 7%, var(--surface));">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-sm font-semibold">Cluster de telefonia</p><p class="mt-1 text-3xl font-bold">{{ $used }} <span class="text-base font-medium" style="color:var(--fg-muted);">de {{ $settings->max_channels }} canais</span></p></div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold" style="color:{{ $stateColor }};background:var(--surface);">{{ number_format($percent, 1, ',', '.') }}% · {{ ['normal'=>'Normal','warning'=>'Alerta','critical'=>'Critico','blocked'=>'Bloqueado'][$state] }}</span>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full" style="background:var(--surface-muted);"><div class="h-full" style="width:{{ min(100, $percent) }}%;background:{{ $stateColor }};"></div></div>
        <p class="mt-3 text-xs" style="color:var(--fg-muted);">Atingir um teto bloqueia somente novas originacoes. Chamadas em andamento e sessoes permanecem ativas.</p>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
        <x-ui.card>
            <div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Limites globais</h2><p class="text-xs" style="color:var(--fg-muted);">Valores exclusivos do SuperAdmin.</p></div></div>
            <form method="POST" action="{{ route('super-admin.capacity.update') }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf @method('PUT')
                @foreach([
                    'max_channels'=>['Canais simultaneos',1], 'warning_percent'=>['Alerta (%)',1], 'critical_percent'=>['Critico (%)',1],
                    'cpu_warning_percent'=>['CPU alerta (%)',1], 'cpu_critical_percent'=>['CPU critica (%)',1],
                    'memory_warning_percent'=>['Memoria alerta (%)',1], 'memory_critical_percent'=>['Memoria critica (%)',1],
                    'database_warning_percent'=>['Banco alerta (%)',1], 'database_critical_percent'=>['Banco critico (%)',1],
                    'telephony_queue_warning_ms'=>['Fila alerta (ms)',100], 'telephony_queue_block_ms'=>['Fila bloqueio (ms)',100],
                ] as $field=>[$label,$min])
                    <label class="grid gap-1 text-sm"><span class="font-semibold">{{ $label }}</span><input type="number" min="{{ $min }}" name="{{ $field }}" value="{{ old($field, $settings->{$field}) }}" required class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"></label>
                @endforeach
                <label class="flex items-center gap-2 rounded-lg border p-3 text-sm md:col-span-2" style="border-color:var(--border);"><input type="hidden" name="origination_blocked" value="0"><input type="checkbox" name="origination_blocked" value="1" @checked($settings->origination_blocked)><span>Pausar novas originacoes manualmente</span></label>
                <div class="flex justify-end md:col-span-3"><x-ui.button type="submit">Salvar protecao</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card>
            <div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Nos Asterisk</h2><p class="text-xs" style="color:var(--fg-muted);">Capacidade fisica e prioridade.</p></div><x-ui.button type="button" x-data x-on:click="$dispatch('open-modal','node-create')">Novo no</x-ui.button></div>
            <div class="mt-4 grid gap-2">@foreach($nodes as $node)
                <button type="button" class="flex w-full items-center justify-between rounded-lg border p-3 text-left" style="border-color:var(--border);" x-data x-on:click="$dispatch('open-modal','node-edit-{{ $node->id }}')">
                    <span><strong>{{ $node->name }}</strong><small class="block" style="color:var(--fg-muted);">{{ $node->bridge_url }} · {{ $node->routes_count }} rotas</small></span><span class="text-sm">{{ $node->active_channels }}/{{ $node->max_channels }} · {{ ucfirst($node->status) }}</span>
                </button>
                <x-ui.modal name="node-edit-{{ $node->id }}" title="Editar no Asterisk" max-width="2xl"><form method="POST" action="{{ route('super-admin.capacity.nodes.update',$node) }}">@csrf @method('PUT') @include('super-admin.capacity._node-form',['node'=>$node])<div class="mt-5 flex justify-end"><x-ui.button type="submit">Salvar no</x-ui.button></div></form></x-ui.modal>
            @endforeach</div>
        </x-ui.card>
    </div>

    <section class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-ui.card><h2 class="text-lg font-semibold">Capacidade por rota</h2><div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="px-2 py-2 text-left">Rota</th><th>No</th><th>Uso</th><th>Empresas</th></tr></thead><tbody>@foreach($routes as $route)<tr class="border-t" style="border-color:var(--border);"><td class="px-2 py-3"><strong>{{ $route->name }}</strong><small class="block" style="color:var(--fg-muted);">{{ filled($route->tech) ? 'Tech '.$route->tech : 'Sem Tech' }}</small></td><td class="px-2">{{ $route->asteriskNode?->name ?? 'Sem no' }}</td><td class="px-2 text-center">{{ $route->channels_in_use }}/{{ $route->max_concurrent_channels ?? 'sem teto' }}</td><td class="px-2 text-center">{{ $route->companies()->count() }}</td></tr>@endforeach</tbody></table></div></x-ui.card>
        <x-ui.card><h2 class="text-lg font-semibold">Consumo por empresa</h2><div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="px-2 py-2 text-left">Empresa</th><th>Em chamada</th><th>Teto</th><th>Peso</th></tr></thead><tbody>@foreach($companies as $company)<tr class="border-t" style="border-color:var(--border);"><td class="px-2 py-3"><strong>{{ $company->name }}</strong></td><td class="px-2 text-center">{{ $company->active_calls }}</td><td class="px-2 text-center">{{ $company->license?->max_concurrent_calls ?? 'sem teto' }}</td><td class="px-2 text-center">{{ $company->resourceLimit?->dialer_weight ?? 1 }}</td></tr>@endforeach</tbody></table></div></x-ui.card>
    </section>

    <x-ui.modal name="node-create" title="Novo no Asterisk" max-width="2xl"><form method="POST" action="{{ route('super-admin.capacity.nodes.store') }}">@csrf @include('super-admin.capacity._node-form',['node'=>null])<div class="mt-5 flex justify-end"><x-ui.button type="submit">Criar no</x-ui.button></div></form></x-ui.modal>
</x-app-layout>
