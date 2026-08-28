<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Team;
use App\Models\TelephonyRoute;
use App\Services\Capacity\ResourceLimitService;
use App\Services\Dialer\MailingQueueService;
use App\Services\Dialer\SimulatedDialerService;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function __construct(private readonly AppNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'finished'])],
            'dialing_mode' => ['nullable', Rule::in(['preview', 'manual', 'power', 'predictive'])],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'list_id' => ['nullable', Rule::exists('contact_lists', 'id')->where('company_id', $companyId)],
        ]);

        $campaigns = Campaign::query()
            ->where('company_id', $companyId)
            ->with(['teams', 'contactLists', 'fixedTelephonyRoute', 'mobileTelephonyRoute'])
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['dialing_mode'] ?? null, fn ($query, $mode) => $query->where('dialing_mode', $mode))
            ->when($filters['team_id'] ?? null, fn ($query, $teamId) => $query->whereHas('teams', fn ($teamQuery) => $teamQuery->where('teams.id', $teamId)))
            ->when($filters['list_id'] ?? null, fn ($query, $listId) => $query->whereHas('contactLists', fn ($listQuery) => $listQuery->where('contact_lists.id', $listId)))
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $queue = app(MailingQueueService::class);
        $dialer = app(SimulatedDialerService::class);

        return view('tenant.campaigns.index', [
            'campaigns' => $campaigns,
            'mailingSummaries' => $campaigns->getCollection()
                ->mapWithKeys(fn (Campaign $campaign): array => [$campaign->id => $queue->summaryForCampaign($campaign)])
                ->all(),
            'scheduleStates' => $campaigns->getCollection()
                ->mapWithKeys(fn (Campaign $campaign): array => [$campaign->id => $dialer->isCampaignInOperationalSchedule($campaign)])
                ->all(),
            'teams' => Team::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'lists' => ContactList::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'telephonyRoutes' => $request->user()->company
                ->telephonyRoutes()
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, ResourceLimitService $limits): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId), $this->validationMessages());
        if (($data['status'] ?? null) === 'active') {
            $limits->assertCampaignCanActivate($companyId);
        }
        $this->validateListSelection($request, $companyId, $data['list_ids'] ?? []);

        $campaign = Campaign::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_script' => $data['service_script'] ?? null,
            'dialing_mode' => $data['dialing_mode'],
            'lead_phone_strategy' => $data['lead_phone_strategy'] ?? 'vertical',
            ...$this->advancedDialingPayload($data),
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 1,
            'operational_schedule' => $this->schedulePayload($data['operational_schedule'] ?? []),
        ]);

        $this->syncRelations($campaign, $data['team_ids'] ?? [], $data['list_ids'] ?? []);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha criada com sucesso.');
    }

    public function update(Request $request, Campaign $campaign, ResourceLimitService $limits): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        $data = $request->validate($this->rules($campaign->company_id, $campaign), $this->validationMessages());
        if (($data['status'] ?? null) === 'active' && $campaign->status !== 'active') {
            $limits->assertCampaignCanActivate($campaign->company_id, $campaign->id);
        }
        $this->validateListSelection($request, $campaign->company_id, $data['list_ids'] ?? []);

        $campaign->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_script' => $data['service_script'] ?? null,
            'dialing_mode' => $data['dialing_mode'],
            'lead_phone_strategy' => $data['lead_phone_strategy'] ?? 'vertical',
            ...$this->advancedDialingPayload($data),
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 1,
            'operational_schedule' => $this->schedulePayload($data['operational_schedule'] ?? []),
        ]);

        $this->syncRelations($campaign, $data['team_ids'] ?? [], $data['list_ids'] ?? []);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha atualizada com sucesso.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        $campaign->update([
            'status' => 'finished',
            'ends_at' => now(),
        ]);
        $this->notifyCampaign($campaign, 'warning', 'Campanha finalizada', "A campanha {$campaign->name} foi finalizada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha finalizada com sucesso.');
    }

    public function start(Request $request, Campaign $campaign, ResourceLimitService $limits): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);
        $limits->assertCampaignCanActivate($campaign->company_id, $campaign->id);

        $campaign->update([
            'status' => 'active',
            'starts_at' => $campaign->starts_at ?? now(),
            'ends_at' => null,
        ]);
        $this->notifyCampaign($campaign, 'success', 'Campanha iniciada', "A campanha {$campaign->name} foi iniciada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha iniciada com sucesso.');
    }

    public function pause(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        $campaign->update(['status' => 'paused']);
        $this->notifyCampaign($campaign, 'warning', 'Campanha pausada', "A campanha {$campaign->name} foi pausada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha pausada com sucesso.');
    }

    public function restart(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        $campaign->update([
            'status' => 'active',
            'starts_at' => $campaign->starts_at ?? now(),
            'ends_at' => null,
        ]);
        $this->notifyCampaign($campaign, 'success', 'Campanha reiniciada', "A campanha {$campaign->name} foi reiniciada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha reiniciada com sucesso.');
    }

    public function duplicate(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        $copy = Campaign::create([
            'company_id' => $campaign->company_id,
            'name' => $this->copyName($campaign),
            'description' => $campaign->description,
            'service_script' => $campaign->service_script,
            'service_fields_schema' => $campaign->service_fields_schema,
            'dialing_mode' => $campaign->dialing_mode,
            'lead_phone_strategy' => $campaign->lead_phone_strategy ?? 'vertical',
            'agent_distribution_strategy' => $campaign->agent_distribution_strategy ?? 'least_idle',
            'max_attempts_per_contact' => $campaign->max_attempts_per_contact,
            'retry_delay_minutes' => $campaign->retry_delay_minutes,
            'wrap_up_seconds' => $campaign->wrap_up_seconds ?? 60,
            'max_concurrent_calls' => null,
            'amd_mode' => $campaign->amd_mode ?? 'disabled',
            'dialing_route' => $campaign->dialing_route,
            'fixed_telephony_route_id' => $campaign->fixed_telephony_route_id,
            'mobile_telephony_route_id' => $campaign->mobile_telephony_route_id,
            'status' => 'draft',
            'priority' => $campaign->priority,
            'operational_schedule' => $campaign->operational_schedule,
        ]);

        $this->syncRelations(
            $copy,
            $campaign->teams()->pluck('teams.id')->all(),
            $campaign->contactLists()->get()->mapWithKeys(fn (ContactList $list): array => [
                $list->id => ['selected' => '1', 'weight' => (int) ($list->pivot->weight ?? 1)],
            ])->all()
        );

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha duplicada com sucesso.');
    }

    public function recycle(Request $request, Campaign $campaign, MailingQueueService $queue): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        $count = $queue->recycleCampaign($campaign);

        return redirect()
            ->route('tenant.campaigns.index')
            ->with('status', sprintf('%d contato reciclado para a fila da campanha.', $count));
    }

    public function dial(Request $request, Campaign $campaign, SimulatedDialerService $dialer): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 404);

        if (! $dialer->isCampaignInOperationalSchedule($campaign)) {
            return redirect()
                ->route('tenant.campaigns.index')
                ->with('status', 'Campanha fora da agenda operacional. Nenhuma chamada foi criada.');
        }

        $count = $dialer->runCampaign($campaign);

        return redirect()
            ->route('tenant.campaigns.index')
            ->with('status', sprintf('%d chamada criada para agentes disponiveis.', $count));
    }

    private function rules(int $companyId, ?Campaign $campaign = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('campaigns')->where('company_id', $companyId)->ignore($campaign)],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_script' => ['nullable', 'string', 'max:5000'],
            'dialing_mode' => ['required', Rule::in(['preview', 'manual', 'power', 'predictive'])],
            'lead_phone_strategy' => ['nullable', Rule::in(['vertical', 'horizontal'])],
            'agent_distribution_strategy' => ['nullable', Rule::in(Campaign::AGENT_DISTRIBUTION_STRATEGIES)],
            'max_attempts_per_contact' => ['nullable', 'integer', 'min:1', 'max:50'],
            'retry_delay_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'wrap_up_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'amd_mode' => ['nullable', Rule::in(['disabled', 'simulated', 'asterisk'])],
            'fixed_telephony_route_id' => ['nullable', 'integer', $this->companyRouteRule($companyId)],
            'mobile_telephony_route_id' => ['nullable', 'integer', $this->companyRouteRule($companyId)],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'finished'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'list_ids' => ['required', 'array', 'min:1'],
            'list_ids.*' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (is_array($value)) {
                        return;
                    }

                    $exists = ContactList::query()
                        ->where('company_id', $companyId)
                        ->where('id', (int) $value)
                        ->exists();

                    if (! $exists) {
                        $fail('Selecione apenas listas desta empresa.');
                    }
                },
            ],
            'operational_schedule' => ['nullable', 'array'],
            'operational_schedule.*.enabled' => ['nullable'],
            'operational_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'operational_schedule.*.end' => ['nullable', 'date_format:H:i'],
        ];
    }

    private function syncRelations(Campaign $campaign, array $teamIds, array $listIds): void
    {
        $campaign->teams()->sync(
            collect($teamIds)->mapWithKeys(fn ($id): array => [(int) $id => ['company_id' => $campaign->company_id]])->all()
        );

        $campaign->contactLists()->sync($this->listSyncPayload($campaign, $listIds));
    }

    private function validateListSelection(Request $request, int $companyId, array $listIds): void
    {
        $ids = collect($listIds)
            ->map(fn ($value, $key): int => is_array($value) ? (int) $key : (int) $value)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $validCount = ContactList::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== $ids->unique()->count()) {
            back()->withErrors(['list_ids.0' => 'Selecione apenas listas desta empresa.'])->throwResponse();
        }
    }

    private function listSyncPayload(Campaign $campaign, array $listIds): array
    {
        return collect($listIds)
            ->filter(fn ($value): bool => ! is_array($value) || filled($value['selected'] ?? null))
            ->mapWithKeys(function ($value, $key) use ($campaign): array {
                if (is_array($value)) {
                    return [(int) $key => [
                        'company_id' => $campaign->company_id,
                        'weight' => max(1, (int) ($value['weight'] ?? 1)),
                    ]];
                }

                return [(int) $value => [
                    'company_id' => $campaign->company_id,
                    'weight' => 1,
                ]];
            })
            ->all();
    }

    private function schedulePayload(array $schedule): ?array
    {
        $payload = collect($schedule)
            ->filter(fn (array $day): bool => filled($day['enabled'] ?? null))
            ->map(fn (array $day): array => [
                'enabled' => true,
                'start' => $day['start'] ?? '09:00',
                'end' => $day['end'] ?? '18:00',
            ])
            ->filter(fn (array $day): bool => $day['start'] < $day['end'])
            ->all();

        return $payload ?: null;
    }

    private function advancedDialingPayload(array $data): array
    {
        return [
            'agent_distribution_strategy' => $data['agent_distribution_strategy'] ?? 'least_idle',
            'max_attempts_per_contact' => $data['max_attempts_per_contact'] ?? null,
            'retry_delay_minutes' => $data['retry_delay_minutes'] ?? null,
            'wrap_up_seconds' => $data['wrap_up_seconds'] ?? 60,
            'max_concurrent_calls' => null,
            'amd_mode' => $data['amd_mode'] ?? 'disabled',
            'fixed_telephony_route_id' => $data['fixed_telephony_route_id'] ?? null,
            'mobile_telephony_route_id' => $data['mobile_telephony_route_id'] ?? null,
            'dialing_route' => null,
        ];
    }

    private function companyRouteRule(int $companyId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
            if (blank($value)) {
                return;
            }

            $isAvailable = TelephonyRoute::query()
                ->whereKey($value)
                ->whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
                ->exists();

            if (! $isAvailable) {
                $fail('Selecione uma rota liberada para a empresa.');
            }
        };
    }

    private function validationMessages(): array
    {
        return [
            'name.required' => 'Informe o nome da campanha.',
            'name.unique' => 'Ja existe uma campanha com este nome nesta empresa.',
            'dialing_mode.required' => 'Selecione o modo de discagem.',
            'dialing_mode.in' => 'Selecione um modo de discagem valido.',
            'status.required' => 'Selecione o status da campanha.',
            'status.in' => 'Selecione um status valido.',
            'team_ids.required' => 'Selecione ao menos uma equipe para a campanha.',
            'team_ids.array' => 'Selecione equipes validas para a campanha.',
            'team_ids.min' => 'Selecione ao menos uma equipe para a campanha.',
            'team_ids.*.exists' => 'Selecione apenas equipes desta empresa.',
            'list_ids.required' => 'Selecione ao menos uma lista para a campanha.',
            'list_ids.array' => 'Selecione listas validas para a campanha.',
            'list_ids.min' => 'Selecione ao menos uma lista para a campanha.',
            'wrap_up_seconds.integer' => 'Informe um tempo de tabulacao em segundos.',
            'wrap_up_seconds.min' => 'O tempo de tabulacao deve ser de pelo menos 10 segundos.',
            'wrap_up_seconds.max' => 'O tempo de tabulacao deve ser de no maximo 3600 segundos.',
        ];
    }

    private function copyName(Campaign $campaign): string
    {
        $baseName = $campaign->name.' - copia';
        $name = $baseName;
        $counter = 2;

        while (Campaign::query()->where('company_id', $campaign->company_id)->where('name', $name)->exists()) {
            $name = $baseName.' '.$counter;
            $counter++;
        }

        return $name;
    }

    private function notifyCampaign(Campaign $campaign, string $severity, string $title, string $message): void
    {
        $this->notifications->createForCompanyAudience(
            $campaign->company_id,
            ['company_admin', 'supervisor'],
            'campaign',
            $severity,
            $title,
            $message,
            route('tenant.campaigns.index')
        );
    }
}
