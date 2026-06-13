<div id="supervision-agents" class="mt-5 grid gap-3">
    @forelse ($agents as $agent)
        @php
            $activeCall = $agent->calls->first();
            $teamsText = count($agent->team_names) ? implode(', ', $agent->team_names) : 'Sem equipe';
        @endphp
        <div id="supervision-agent-{{ $agent->id }}" class="rounded-lg border p-4" style="border-color: var(--border);">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_260px] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">{{ $agent->name }}</p>
                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $agent->supervision_status }}</span>
                        @foreach ($agent->operational_alerts as $alert)
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: color-mix(in srgb, var(--danger) 14%, var(--surface)); color: var(--danger);">{{ $alert }}</span>
                        @endforeach
                    </div>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                        Ramal {{ $agent->extension }} | {{ $agent->user_type }} | {{ $teamsText }}
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-lg border p-2" style="border-color: var(--border); background: var(--surface-alt);">
                            <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Tempo no status</p>
                            <p class="mt-1 text-sm font-semibold">{{ $agent->status_duration_label }}</p>
                        </div>
                        <div class="rounded-lg border p-2" style="border-color: var(--border);">
                            <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Campanha atual</p>
                            <p class="mt-1 text-sm font-semibold">{{ $agent->current_campaign_name ?? 'Sem campanha' }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-sm">
                    @if ($activeCall)
                        <p class="font-semibold">Chamada ativa</p>
                        <p style="color: var(--fg-muted);">{{ $activeCall->phone ?? 'Sem numero' }} | {{ $activeCall->channel }}</p>
                        <p class="mt-2 text-xs font-semibold" style="color: var(--fg);">Tempo em chamada</p>
                        <p class="text-xs" style="color: var(--fg-muted);">{{ $agent->active_call_duration_label }}</p>
                    @else
                        <p class="font-semibold">Sem chamada</p>
                        <p style="color: var(--fg-muted);">Aguardando evento</p>
                    @endif
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'supervision-agent-{{ $agent->id }}')">Detalhes</x-ui.button>
                    <span class="rounded-lg border px-3 py-2 text-xs font-semibold" style="border-color: var(--border); color: var(--fg-muted);">Ouvir - Em breve</span>
                    <span class="rounded-lg border px-3 py-2 text-xs font-semibold" style="border-color: var(--border); color: var(--fg-muted);">Sussurrar - Em breve</span>
                    <span class="rounded-lg border px-3 py-2 text-xs font-semibold" style="border-color: var(--border); color: var(--fg-muted);">Intervir - Em breve</span>
                </div>
            </div>
        </div>

        <x-ui.modal name="supervision-agent-{{ $agent->id }}" title="Detalhes do agente" max-width="md">
            <div class="grid gap-3 text-sm">
                <p><span class="font-semibold">Nome:</span> {{ $agent->name }}</p>
                <p><span class="font-semibold">Ramal:</span> {{ $agent->extension }}</p>
                <p><span class="font-semibold">Status:</span> {{ $agent->supervision_status }}</p>
                <p><span class="font-semibold">Tempo no status:</span> {{ $agent->status_duration_label }}</p>
                <p><span class="font-semibold">Equipes:</span> {{ $teamsText }}</p>
                <p><span class="font-semibold">Campanha atual:</span> {{ $agent->current_campaign_name ?? 'Sem campanha' }}</p>
                <p><span class="font-semibold">Tempo em chamada:</span> {{ $agent->active_call_duration_label ?? 'Sem chamada' }}</p>
                @if (count($agent->operational_alerts))
                    <p><span class="font-semibold">Alertas:</span> {{ implode(', ', $agent->operational_alerts) }}</p>
                @endif
                <p><span class="font-semibold">Telefonia:</span> Ouvir, Sussurrar e Intervir em breve.</p>
            </div>
        </x-ui.modal>
    @empty
        <p class="text-sm" style="color: var(--fg-muted);">Nenhum operador encontrado.</p>
    @endforelse
</div>
