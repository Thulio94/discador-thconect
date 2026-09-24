@php
    $initialTab = request('tab') === 'schedules' ? 'schedules' : 'files';
    $schedules = $reportData['schedules'];
    $agents = $options['agents'];
    $campaigns = $options['campaigns'];
    $lists = $options['lists'];
    $teams = $options['teams'];
    $dispositions = $options['dispositions'];
@endphp

<x-ui.card>
    <div x-data="{ tab: '{{ $initialTab }}' }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Histórico de exportações</h2>
            <p class="text-xs" style="color: var(--fg-muted);">Arquivos são processados em fila e ficam disponíveis por 30 dias.</p>
        </div>
        <div class="flex gap-2" role="tablist" aria-label="Histórico de exportações">
            <button type="button" class="th-focus rounded-lg px-3 py-2 text-sm" x-on:click="tab='files'" x-bind:style="tab==='files' ? 'background:var(--primary);color:white' : ''">Fila e arquivos</button>
            <button type="button" class="th-focus rounded-lg px-3 py-2 text-sm" x-on:click="tab='schedules'" x-bind:style="tab==='schedules' ? 'background:var(--primary);color:white' : ''">Programações</button>
        </div>
    </div>

    <div class="mt-4" x-show="tab==='files'">
        <div class="mb-3 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border p-3" style="border-color: var(--border);">Em processamento <strong class="ml-2">{{ $reportData['metrics']['queued'] }}</strong></div>
            <div class="rounded-lg border p-3" style="border-color: var(--border);">Concluídas <strong class="ml-2">{{ $reportData['metrics']['completed'] }}</strong></div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead style="background: var(--bg-subtle); color: var(--fg-muted);"><tr><th class="px-3 py-3">Relatório</th><th class="px-3 py-3">Formato</th><th class="px-3 py-3">Solicitante</th><th class="px-3 py-3">Estado</th><th class="px-3 py-3">Registros</th><th class="px-3 py-3">Criado</th><th class="px-3 py-3"></th></tr></thead>
                <tbody class="divide-y" style="border-color: var(--border);">
                    @forelse ($reportData['rows'] as $export)
                        <tr>
                            <td class="px-3 py-3">{{ $reportCatalog[$export->report_type]['title'] ?? $export->report_type }}</td>
                            <td class="px-3 py-3">{{ strtoupper($export->format) }}</td>
                            <td class="px-3 py-3">{{ $export->user?->name ?? 'Sistema' }}</td>
                            <td class="px-3 py-3">{{ ['queued' => 'Aguardando', 'processing' => 'Processando', 'completed' => 'Concluído', 'failed' => 'Falhou', 'expired' => 'Expirado'][$export->status] ?? $export->status }} <span class="text-xs" style="color: var(--fg-muted);">{{ $export->progress }}%</span>@if ($export->error_message)<span class="mt-1 block text-xs" style="color: var(--status-danger);">{{ $export->error_message }}</span>@endif</td>
                            <td class="px-3 py-3">{{ $export->records_count }}</td>
                            <td class="px-3 py-3">{{ $export->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-3">@if ($export->status === 'completed' && (! $export->expires_at || $export->expires_at->isFuture()))<a href="{{ route('tenant.reports.exports.download', $export) }}" class="th-focus rounded border px-2 py-1 text-xs" style="border-color: var(--border);">Baixar</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center">Nenhuma exportação registrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reportData['rows']->links() }}</div>
    </div>

    <div class="mt-4" x-show="tab==='schedules'" x-cloak>
        @can('tenant.reports.schedules')
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><h3 class="font-semibold">Agendamentos de relatórios</h3><p class="text-xs" style="color: var(--fg-muted);">Rotinas diárias e semanais usam a mesma fila de exportação.</p></div>
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'report-schedule-create')">Novo agendamento</x-ui.button>
            </div>
        @endcan

        <div class="grid gap-3">
            @forelse ($schedules as $schedule)
                <div class="grid gap-4 rounded-lg border p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center" style="border-color: var(--border);">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2"><p class="font-semibold">{{ $schedule->name }}</p><span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle);">{{ strtoupper($schedule->format) }}</span><span class="rounded-full px-2 py-1 text-xs" style="background: var(--primary-soft);">{{ $schedule->status_label }}</span></div>
                        <p class="mt-2 text-sm">{{ $reportCatalog[$schedule->report_type]['title'] ?? 'Relatório de ligações' }} · {{ $schedule->frequency_label }} às {{ substr($schedule->run_time, 0, 5) }}</p>
                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $schedule->filter_label }} · {{ $schedule->last_run_label }}</p>
                    </div>
                    @can('tenant.reports.schedules')
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <form method="POST" action="{{ route('tenant.reports.schedules.run', $schedule) }}">@csrf <x-ui.button type="submit" :disabled="$schedule->status !== 'active'">Executar agora</x-ui.button></form>
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'report-schedule-view-{{ $schedule->id }}')" />
                            <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'report-schedule-edit-{{ $schedule->id }}')" />
                            <x-ui.action icon="delete" label="Excluir" variant="danger" x-data x-on:click="$dispatch('open-modal', 'report-schedule-delete-{{ $schedule->id }}')" />
                            <form method="POST" action="{{ route('tenant.reports.schedules.status', $schedule) }}">@csrf @method('PATCH') <x-ui.button type="submit" variant="secondary">{{ $schedule->status === 'active' ? 'Inativar' : 'Ativar' }}</x-ui.button></form>
                        </div>
                    @endcan
                </div>
            @empty
                <p class="py-8 text-center text-sm" style="color: var(--fg-muted);">Nenhuma programação cadastrada.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $schedules->links() }}</div>
    </div>
    </div>
</x-ui.card>

@can('tenant.reports.schedules')
    <x-ui.modal name="report-schedule-create" title="Novo agendamento" max-width="4xl">
        <form method="POST" action="{{ route('tenant.reports.schedules.store') }}">@csrf @include('tenant.reports._schedule-form', ['schedule' => null])<div class="mt-5 flex justify-end"><x-ui.button type="submit">Criar agendamento</x-ui.button></div></form>
    </x-ui.modal>

    @foreach ($schedules as $schedule)
        <x-ui.modal name="report-schedule-view-{{ $schedule->id }}" title="Detalhes do agendamento" max-width="lg">
            <dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="font-semibold">Nome</dt><dd>{{ $schedule->name }}</dd></div><div><dt class="font-semibold">Relatório</dt><dd>{{ $reportCatalog[$schedule->report_type]['title'] ?? 'Relatório de ligações' }}</dd></div><div><dt class="font-semibold">Estado</dt><dd>{{ $schedule->status_label }}</dd></div><div><dt class="font-semibold">Formato</dt><dd>{{ strtoupper($schedule->format) }}</dd></div><div class="sm:col-span-2"><dt class="font-semibold">Filtros</dt><dd>{{ $schedule->filter_label }}</dd></div></dl>
        </x-ui.modal>
        <x-ui.modal name="report-schedule-edit-{{ $schedule->id }}" title="Editar agendamento" max-width="4xl">
            <form method="POST" action="{{ route('tenant.reports.schedules.update', $schedule) }}">@csrf @method('PUT') @include('tenant.reports._schedule-form', ['schedule' => $schedule])<div class="mt-5 flex justify-end"><x-ui.button type="submit">Salvar alterações</x-ui.button></div></form>
        </x-ui.modal>
        <x-ui.modal name="report-schedule-delete-{{ $schedule->id }}" title="Excluir agendamento" max-width="md">
            <form method="POST" action="{{ route('tenant.reports.schedules.destroy', $schedule) }}">@csrf @method('DELETE')<p class="text-sm" style="color: var(--fg-muted);">O agendamento {{ $schedule->name }} será removido permanentemente.</p><div class="mt-5 flex justify-end gap-2"><x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'report-schedule-delete-{{ $schedule->id }}')">Cancelar</x-ui.button><x-ui.button type="submit" variant="danger">Excluir agendamento</x-ui.button></div></form>
        </x-ui.modal>
    @endforeach
@endcan
