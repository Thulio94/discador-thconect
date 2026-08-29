<?php

namespace App\Console\Commands;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\SimulatedDialerService;
use App\Services\Users\ExtensionAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunDialerSmokeLoad extends Command
{
    protected $signature = 'dialer:smoke-load
        {--agents=5,10 : Quantidades de agentes separadas por virgula}
        {--calls-per-agent=10 : Tentativas simultaneas por agente disponivel}';

    protected $description = 'Executa uma carga simulada e reversivel do discador sem originar chamadas reais';

    public function handle(
        SimulatedDialerService $dialer,
        ExtensionAllocator $extensions,
    ): int {
        $agentCounts = collect(explode(',', (string) $this->option('agents')))
            ->map(fn (string $value): int => (int) trim($value))
            ->filter()
            ->unique()
            ->values();
        $callsPerAgent = (int) $this->option('calls-per-agent');

        if ($agentCounts->isEmpty() || $agentCounts->contains(fn (int $count): bool => $count < 1 || $count > 10)) {
            $this->error('Informe entre 1 e 10 agentes por cenario. Exemplo: --agents=5,10');

            return self::FAILURE;
        }

        if ($callsPerAgent < 1 || $callsPerAgent > 10) {
            $this->error('As tentativas por agente devem estar entre 1 e 10.');

            return self::FAILURE;
        }

        $this->components->info('Teste isolado: nenhuma chamada real sera originada e todos os dados serao revertidos.');
        $rows = [];

        try {
            foreach ($agentCounts as $agentCount) {
                $rows[] = $this->runScenario($dialer, $extensions, $agentCount, $callsPerAgent);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->newLine();
            $this->error('Teste interrompido: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table([
            'Agentes',
            'Tent./agente',
            'Chamadas',
            'Ativas',
            'Canceladas',
            'Preparacao',
            'Discagem',
            'Chamadas/s',
        ], $rows);
        $this->newLine();
        $this->components->info('Carga simulada concluida sem deixar registros no banco.');

        return self::SUCCESS;
    }

    /** @return array<int, int|string> */
    private function runScenario(
        SimulatedDialerService $dialer,
        ExtensionAllocator $extensions,
        int $agentCount,
        int $callsPerAgent,
    ): array {
        $expectedCalls = $agentCount * $callsPerAgent;
        $transactionLevel = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $setupStartedAt = hrtime(true);
            [$campaign, $company] = $this->fixture($extensions, $agentCount, $callsPerAgent);
            $setupMilliseconds = $this->elapsedMilliseconds($setupStartedAt);

            $dialStartedAt = hrtime(true);
            $created = $dialer->runCampaign($campaign);
            $dialMilliseconds = $this->elapsedMilliseconds($dialStartedAt);
            $active = Call::query()
                ->where('company_id', $company->id)
                ->where('campaign_id', $campaign->id)
                ->where('status', 'in_progress')
                ->count();
            $cancelled = Call::query()
                ->where('company_id', $company->id)
                ->where('campaign_id', $campaign->id)
                ->where('status', 'cancelled')
                ->count();
            $onlineInCall = AgentStatus::query()
                ->where('company_id', $company->id)
                ->whereNull('ended_at')
                ->where('status', 'in_call')
                ->count();

            if (
                $created !== $expectedCalls
                || $active !== $agentCount
                || $cancelled !== $expectedCalls - $agentCount
                || $onlineInCall !== $agentCount
            ) {
                throw new \RuntimeException(sprintf(
                    'Capacidade divergente no cenario de %d agentes: criadas=%d, ativas=%d, canceladas=%d, em_chamada=%d.',
                    $agentCount,
                    $created,
                    $active,
                    $cancelled,
                    $onlineInCall,
                ));
            }

            $callsPerSecond = $dialMilliseconds > 0
                ? round($created / ($dialMilliseconds / 1000), 1)
                : $created;

            return [
                $agentCount,
                $callsPerAgent,
                $created,
                $active,
                $cancelled,
                $setupMilliseconds.' ms',
                $dialMilliseconds.' ms',
                $callsPerSecond,
            ];
        } finally {
            while (DB::transactionLevel() > $transactionLevel) {
                DB::rollBack();
            }
        }
    }

    /** @return array{Campaign, Company} */
    private function fixture(
        ExtensionAllocator $extensions,
        int $agentCount,
        int $callsPerAgent,
    ): array {
        $token = Str::lower(Str::random(10));
        $company = Company::query()->create([
            'name' => 'Teste de carga '.$token,
            'status' => 'active',
        ]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addDay(),
            'max_calls_per_available_agent' => $callsPerAgent,
            'max_concurrent_agents' => $agentCount,
            'max_concurrent_calls' => $agentCount * $callsPerAgent,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'Agente carga',
            'slug' => 'agente-carga-'.$token,
            'is_admin' => false,
        ]);
        $team = Team::query()->create([
            'company_id' => $company->id,
            'name' => 'Equipe carga',
            'slug' => 'equipe-carga-'.$token,
            'status' => 'active',
        ]);
        $campaign = Campaign::query()->create([
            'company_id' => $company->id,
            'name' => 'Campanha carga '.$token,
            'dialing_mode' => 'power',
            'status' => 'active',
            'aggressiveness' => $callsPerAgent,
        ]);
        $list = ContactList::query()->create([
            'company_id' => $company->id,
            'name' => 'Mailing carga '.$token,
            'status' => 'active',
        ]);

        for ($index = 1; $index <= $agentCount; $index++) {
            $agent = User::query()->create([
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => sprintf('Agente carga %02d', $index),
                'email' => sprintf('carga-%s-%02d@invalid.local', $token, $index),
                'password' => Str::random(40),
                'user_type' => 'agent',
                'extension' => $extensions->allocate(),
                'status' => 'active',
            ]);
            $team->users()->attach($agent, [
                'company_id' => $company->id,
                'membership_type' => 'agent',
            ]);
            AgentStatus::query()->create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'status' => 'available',
                'started_at' => now(),
            ]);
        }

        $contactCount = $agentCount * $callsPerAgent;

        for ($index = 1; $index <= $contactCount; $index++) {
            $phone = '319'.str_pad((string) $index, 8, '0', STR_PAD_LEFT);
            $contact = Contact::query()->create([
                'company_id' => $company->id,
                'contact_list_id' => $list->id,
                'name' => sprintf('Lead carga %03d', $index),
                'phone' => $phone,
                'status' => 'pending',
            ]);
            $contact->phones()->create([
                'company_id' => $company->id,
                'phone' => $phone,
                'column_name' => 'telefone',
                'position' => 0,
                'is_primary' => true,
                'status' => 'pending',
            ]);
        }

        $campaign->teams()->attach($team, ['company_id' => $company->id]);
        $campaign->contactLists()->attach($list, [
            'company_id' => $company->id,
            'weight' => 1,
        ]);

        return [$campaign, $company];
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
