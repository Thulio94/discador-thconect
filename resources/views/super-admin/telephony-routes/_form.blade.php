@props(['route' => null])

<div class="grid gap-4 md:grid-cols-2" x-data="{ auth: '{{ old('authentication_mode', $route?->authentication_mode ?? 'ip') }}' }">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">No Asterisk</span>
        <select name="asterisk_node_id" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            @foreach($asteriskNodes as $node)
                <option value="{{ $node->id }}" @selected((int) old('asterisk_node_id', $route?->asterisk_node_id) === $node->id)>{{ $node->name }} · {{ $node->max_channels }} canais</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Canais contratados</span>
        <input type="number" min="1" max="5000" name="max_concurrent_channels" value="{{ old('max_concurrent_channels', $route?->max_concurrent_channels) }}" placeholder="Sem teto proprio" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome da rota</span>
        <input name="name" value="{{ old('name', $route?->name) }}" placeholder="SMP Classificada" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Operadora</span>
        <input name="provider" value="{{ old('provider', $route?->provider) }}" placeholder="Solutions VoIP" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Tech</span>
        <input name="tech" inputmode="numeric" value="{{ old('tech', $route?->tech) }}" placeholder="5006" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        <span class="text-xs" style="color: var(--fg-muted);">Prefixo da discagem. Ex.: Tech + 55 + DDD + telefone.</span>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Dominio ou IP SIP</span>
        <input name="host" value="{{ old('host', $route?->host) }}" placeholder="sbc.operadora.com.br" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">From Domain</span>
        <input name="from_domain" value="{{ old('from_domain', $route?->from_domain) }}" placeholder="sbc.operadora.com.br" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Transporte SIP</span>
        <select name="transport" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <option value="udp" @selected(old('transport', $route?->transport ?? 'udp') === 'udp')>UDP</option>
            <option value="tcp" @selected(old('transport', $route?->transport) === 'tcp')>TCP</option>
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Autenticacao da operadora</span>
        <select name="authentication_mode" x-model="auth" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <option value="ip">Por IP liberado</option>
            <option value="registration">Usuario e senha SIP</option>
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Caller ID</span>
        <input name="caller_id" value="{{ old('caller_id', $route?->caller_id) }}" placeholder="Numero autorizado" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration'" x-cloak>
        <span class="font-semibold">Usuario SIP</span>
        <input name="sip_username" autocomplete="off" value="{{ old('sip_username', $route?->sip_username) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration'" x-cloak>
        <span class="font-semibold">Senha SIP</span>
        <input name="sip_password" type="password" autocomplete="new-password" placeholder="{{ $route?->sip_password ? 'Mantida com seguranca' : 'Senha SIP' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Codecs</span>
        <input name="codecs" value="{{ old('codecs', $route?->codecs ?? 'alaw,g729') }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Contexto Asterisk</span>
        <input name="dialplan_context" value="{{ old('dialplan_context', $route?->dialplan_context ?? 'thconect-outbound') }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Extensao Asterisk</span>
        <input name="dialplan_extension" value="{{ old('dialplan_extension', $route?->dialplan_extension ?? 's') }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="flex items-center gap-2 self-end rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border);">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $route?->enabled))>
        <span>Habilitar rota para testes e chamadas</span>
    </label>
</div>
