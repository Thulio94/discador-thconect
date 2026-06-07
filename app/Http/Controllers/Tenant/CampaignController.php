<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Team;
use App\Services\Dialer\MailingQueueService;
use App\Services\Dialer\SimulatedDialerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        $campaigns = Campaign::query()
            ->where('company_id', $companyId)
            ->with(['teams', 'contactLists'])
            ->latest()
            ->paginate(12);
        $queue = app(MailingQueueService::class);

        return view('tenant.campaigns.index', [
            'campaigns' => $campaigns,
            'mailingSummaries' => $campaigns->getCollection()
                ->mapWithKeys(fn (Campaign $campaign): array => [$campaign->id => $queue->summaryForCampaign($campaign)])
                ->all(),
            'teams' => Team::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'lists' => ContactList::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId));

        $campaign = Campaign::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_script' => $data['service_script'] ?? null,
            'dialing_mode' => $data['dialing_mode'],
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 1,
        ]);

        $this->syncRelations($campaign, $data['team_ids'] ?? [], $data['list_ids'] ?? []);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha criada com sucesso.');
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $data = $request->validate($this->rules($campaign->company_id, $campaign));

        $campaign->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_script' => $data['service_script'] ?? null,
            'dialing_mode' => $data['dialing_mode'],
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 1,
        ]);

        $this->syncRelations($campaign, $data['team_ids'] ?? [], $data['list_ids'] ?? []);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha atualizada com sucesso.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update([
            'status' => 'finished',
            'ends_at' => now(),
        ]);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha finalizada com sucesso.');
    }

    public function start(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update([
            'status' => 'active',
            'starts_at' => $campaign->starts_at ?? now(),
            'ends_at' => null,
        ]);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha iniciada com sucesso.');
    }

    public function pause(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update(['status' => 'paused']);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha pausada com sucesso.');
    }

    public function restart(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update([
            'status' => 'active',
            'starts_at' => $campaign->starts_at ?? now(),
            'ends_at' => null,
        ]);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha reiniciada com sucesso.');
    }

    public function duplicate(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $copy = Campaign::create([
            'company_id' => $campaign->company_id,
            'name' => $this->copyName($campaign),
            'description' => $campaign->description,
            'service_script' => $campaign->service_script,
            'dialing_mode' => $campaign->dialing_mode,
            'status' => 'draft',
            'priority' => $campaign->priority,
        ]);

        $this->syncRelations(
            $copy,
            $campaign->teams()->pluck('teams.id')->all(),
            $campaign->contactLists()->get()->mapWithKeys(fn (ContactList $list): array => [
                $list->id => ['weight' => (int) ($list->pivot->weight ?? 1)],
            ])->all()
        );

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha duplicada com sucesso.');
    }

    public function recycle(Request $request, Campaign $campaign, MailingQueueService $queue): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $count = $queue->recycleCampaign($campaign);

        return redirect()
            ->route('tenant.campaigns.index')
            ->with('status', sprintf('%d contato reciclado para a fila da campanha.', $count));
    }

    public function dial(Request $request, Campaign $campaign, SimulatedDialerService $dialer): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $count = $dialer->runCampaign($campaign);

        return redirect()
            ->route('tenant.campaigns.index')
            ->with('status', sprintf('%d chamada simulada criada para agentes disponiveis.', $count));
    }

    private function rules(int $companyId, ?Campaign $campaign = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('campaigns')->where('company_id', $companyId)->ignore($campaign)],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_script' => ['nullable', 'string', 'max:5000'],
            'dialing_mode' => ['required', Rule::in(['preview', 'manual', 'power', 'predictive'])],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'finished'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'list_ids' => ['required', 'array', 'min:1'],
            'list_ids.*' => [Rule::exists('contact_lists', 'id')->where('company_id', $companyId)],
        ];
    }

    private function syncRelations(Campaign $campaign, array $teamIds, array $listIds): void
    {
        $campaign->teams()->sync(
            collect($teamIds)->mapWithKeys(fn ($id): array => [(int) $id => ['company_id' => $campaign->company_id]])->all()
        );

        $campaign->contactLists()->sync($this->listSyncPayload($campaign, $listIds));
    }

    private function listSyncPayload(Campaign $campaign, array $listIds): array
    {
        return collect($listIds)
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
}
