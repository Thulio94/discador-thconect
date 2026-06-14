# Simulated Dialer Concurrency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fazer o discador simulado criar lotes de tentativas conforme a licenca, manter somente uma chamada atendida por agente e compartilhar o teto simultaneo entre todas as campanhas da empresa.

**Architecture:** `AgentCapacityService` sera a fonte unica dos limites e vagas restantes. `SimulatedDialerService` executara cada campanha em uma transacao bloqueada pela licenca da empresa, criando uma chamada vencedora por agente e tentativas excedentes canceladas com motivo auditavel. Dashboard, empresas e relatorios reutilizarao os dados persistidos sem novas paginas.

**Tech Stack:** PHP 8.3, Laravel 12, Eloquent, MariaDB, Blade, PHPUnit, Docker Compose, Vite.

---

## Estrutura de Arquivos

- Criar `database/migrations/2026_06_14_000001_add_end_reason_to_calls_table.php`: persistir motivo tecnico do encerramento.
- Modificar `app/Models/Call.php`: permitir preenchimento de `end_reason`.
- Modificar `app/Services/Dialer/AgentCapacityService.php`: calcular uso ativo, capacidade contratada e vagas globais.
- Modificar `app/Services/Dialer/SimulatedDialerService.php`: processar lotes por agente dentro de transacao bloqueada.
- Modificar `app/Http/Controllers/DashboardController.php`: fornecer uso atual da licenca.
- Modificar `app/Http/Controllers/SuperAdmin/CompanyController.php`: carregar contagem de chamadas ativas pela relacao existente da empresa.
- Modificar `app/Http/Controllers/Tenant/ReportController.php`: excluir canceladas de TMA e apresentar motivo tecnico.
- Modificar `resources/views/dashboard.blade.php`: mostrar limite por operador e uso do teto global.
- Modificar `resources/views/super-admin/companies/index.blade.php`: mostrar uso contratado versus atual.
- Modificar `resources/views/tenant/reports/index.blade.php`: exibir estado e motivo das tentativas canceladas.
- Modificar `tests/Feature/SaaSDataModelTest.php`: testar persistencia do motivo tecnico.
- Modificar `tests/Feature/TenantAgentPanelTest.php`: testar capacidade e lotes do simulador.
- Modificar `tests/Feature/SuperAdminCompanyCrudTest.php`: testar indicador de uso.
- Modificar `tests/Feature/CoreRoutesTest.php`: testar indicador do dashboard tenant.
- Modificar `tests/Feature/TenantReportsTest.php`: testar metricas e exibicao de cancelamentos.
- Modificar `docs/ROADMAP_CONCLUSAO.md`: registrar simultaneidade efetiva.

### Task 1: Persistir o motivo tecnico da chamada

**Files:**
- Create: `database/migrations/2026_06_14_000001_add_end_reason_to_calls_table.php`
- Modify: `app/Models/Call.php`
- Test: `tests/Feature/SaaSDataModelTest.php`

- [ ] **Step 1: Escrever o teste RED**

Adicionar a `SaaSDataModelTest`:

```php
public function test_call_persists_technical_end_reason(): void
{
    $company = Company::create(['name' => 'Tenant Motivo']);

    $call = Call::create([
        'company_id' => $company->id,
        'direction' => 'outbound',
        'status' => 'cancelled',
        'channel' => 'simulated',
        'phone' => '31999990000',
        'started_at' => now(),
        'ended_at' => now(),
        'end_reason' => 'answered_elsewhere',
    ]);

    $this->assertSame('answered_elsewhere', $call->fresh()->end_reason);
}
```

- [ ] **Step 2: Executar o teste e confirmar a falha**

Executar:

```powershell
docker compose exec -T app php artisan test tests/Feature/SaaSDataModelTest.php --filter=technical_end_reason
```

Esperado: `FAIL`, porque `calls.end_reason` ainda nao existe.

- [ ] **Step 3: Criar a migracao**

Criar:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->string('end_reason')->nullable()->after('status');
            $table->index(['company_id', 'end_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'end_reason']);
            $table->dropColumn('end_reason');
        });
    }
};
```

Adicionar `end_reason` ao atributo `Fillable` de `Call`.

- [ ] **Step 4: Executar o teste e confirmar sucesso**

Executar:

```powershell
docker compose exec -T app php artisan test tests/Feature/SaaSDataModelTest.php --filter=technical_end_reason
```

Esperado: `PASS`.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations/2026_06_14_000001_add_end_reason_to_calls_table.php app/Models/Call.php tests/Feature/SaaSDataModelTest.php
git commit -m "feat: persist call end reason"
```

### Task 2: Calcular capacidade restante por empresa

**Files:**
- Modify: `app/Services/Dialer/AgentCapacityService.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Escrever os testes RED**

Adicionar testes que criem uma empresa com `max_calls_per_available_agent = 3` e `max_concurrent_calls = 5`.

O primeiro deve comprovar:

```php
$this->assertSame(2, $service->activeCallsForCompany($company->id));
$this->assertSame(3, $service->remainingConcurrentCallsForCampaign($campaign));
```

O segundo deve criar uma chamada ativa em outra empresa e comprovar:

```php
$this->assertSame(5, $service->remainingConcurrentCallsForCampaign($campaign));
```

O terceiro deve definir `max_concurrent_calls = null`, dois agentes elegiveis e comprovar:

```php
$this->assertSame(6, $service->remainingConcurrentCallsForCampaign($campaign));
```

- [ ] **Step 2: Executar os testes e confirmar a falha**

Executar:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter=remaining_concurrent
```

Esperado: `FAIL` por metodos inexistentes.

- [ ] **Step 3: Implementar os calculos minimos**

Adicionar a `AgentCapacityService`:

```php
public function activeCallsForCompany(int $companyId): int
{
    return Call::query()
        ->where('company_id', $companyId)
        ->where('status', 'in_progress')
        ->count();
}

public function remainingConcurrentCallsForCampaign(Campaign $campaign): int
{
    $campaign->loadMissing('company.license');

    $license = $campaign->company?->license;
    $campaignCapacity = $this->allowedCallsForCampaign($campaign);
    $globalLimit = $license?->max_concurrent_calls;

    if (! $globalLimit) {
        return $campaignCapacity;
    }

    return max(0, min(
        $campaignCapacity,
        $globalLimit - $this->activeCallsForCompany($campaign->company_id),
    ));
}
```

Importar `App\Models\Call`.

Alterar `allowedCallsForCampaign()` para calcular somente
`availableAgentsForCampaign * max_calls_per_available_agent`; o teto global passa a ser aplicado exclusivamente em `remainingConcurrentCallsForCampaign()`.

- [ ] **Step 4: Executar os testes de capacidade**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter="capacity|remaining_concurrent"
```

Esperado: todos `PASS`.

- [ ] **Step 5: Commit**

```powershell
git add app/Services/Dialer/AgentCapacityService.php tests/Feature/TenantAgentPanelTest.php
git commit -m "feat: calculate remaining company call capacity"
```

### Task 3: Criar lotes com uma chamada vencedora por agente

**Files:**
- Modify: `app/Services/Dialer/SimulatedDialerService.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Escrever o teste RED do lote**

Criar dois agentes disponiveis, seis contatos e uma licenca com tres chamadas por agente. Executar a campanha e validar:

```php
$this->assertSame(6, $created);
$this->assertSame(2, Call::where('campaign_id', $campaign->id)->where('status', 'in_progress')->count());
$this->assertSame(4, Call::where('campaign_id', $campaign->id)->where('status', 'cancelled')->count());
$this->assertSame(
    4,
    Call::where('campaign_id', $campaign->id)
        ->where('end_reason', 'answered_elsewhere')
        ->count(),
);
$this->assertSame(
    2,
    Call::where('campaign_id', $campaign->id)
        ->where('status', 'in_progress')
        ->distinct('user_id')
        ->count('user_id'),
);
```

Validar ainda que as canceladas possuem `answered_at = null`, `ended_at` preenchido e que os seis contatos receberam uma tentativa.

- [ ] **Step 2: Executar o teste e confirmar a falha**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter=creates_concurrent_batches
```

Esperado: `FAIL`, pois o motor atual cria apenas uma chamada por agente.

- [ ] **Step 3: Implementar o lote minimo**

Refatorar `runCampaign()` para:

```php
public function runCampaign(Campaign $campaign): int
{
    if ($campaign->status !== 'active' || ! $this->isCampaignInOperationalSchedule($campaign)) {
        return 0;
    }

    return DB::transaction(function () use ($campaign): int {
        $campaign->company->license()->lockForUpdate()->first();
        $campaign->unsetRelation('company');

        $remaining = $this->capacity->remainingConcurrentCallsForCampaign($campaign);

        if ($remaining < 1) {
            return 0;
        }

        $callsPerAgent = max(
            1,
            (int) ($campaign->company?->license?->max_calls_per_available_agent ?? 1),
        );
        $created = 0;

        foreach ($this->availableAgents($campaign)->get() as $agent) {
            if ($remaining < 1) {
                break;
            }

            $batchSize = min($callsPerAgent, $remaining);
            $batchCreated = $this->createBatch($campaign, $agent, $batchSize);
            $created += $batchCreated;
            $remaining -= $batchCreated;
        }

        return $created;
    }, 3);
}
```

Criar `createBatch()` para buscar contatos um a um. A primeira tentativa criada usa:

```php
[
    'status' => 'in_progress',
    'answered_at' => now(),
    'ended_at' => null,
    'end_reason' => null,
]
```

As demais usam:

```php
[
    'status' => 'cancelled',
    'answered_at' => null,
    'ended_at' => now(),
    'end_reason' => 'answered_elsewhere',
]
```

Somente depois da primeira tentativa valida, fechar registros anteriores do agente e criar o status `in_call`. Para cada contato criado, chamar `markAttempt()`.

- [ ] **Step 4: Executar o teste do lote**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter=creates_concurrent_batches
```

Esperado: `PASS`.

- [ ] **Step 5: Commit**

```powershell
git add app/Services/Dialer/SimulatedDialerService.php tests/Feature/TenantAgentPanelTest.php
git commit -m "feat: create simulated concurrent call batches"
```

### Task 4: Aplicar teto global, fila reduzida e elegibilidade

**Files:**
- Modify: `app/Services/Dialer/SimulatedDialerService.php`
- Modify: `app/Services/Dialer/AgentCapacityService.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Escrever testes RED de limites**

Adicionar testes independentes para:

1. uma chamada ativa em outra campanha e teto quatro deixam somente tres novas tentativas;
2. chamada ativa de outra empresa nao reduz o lote;
3. dois contatos com capacidade seis criam somente duas tentativas;
4. agente com chamada `ended` sem `disposition_id` nao recebe lote;
5. supervisor e administrador disponiveis nao recebem lote.

As assercoes centrais devem ser:

```php
$this->assertSame(3, $dialer->runCampaign($campaign));
$this->assertLessThanOrEqual(
    4,
    Call::where('company_id', $company->id)->where('status', 'in_progress')->count()
        + Call::where('company_id', $company->id)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $runStartedAt)
            ->count(),
);
```

Para o caso sem tabulacao:

```php
$this->assertSame(0, $dialer->runCampaign($campaign));
$this->assertSame('pending', $contact->fresh()->status);
```

- [ ] **Step 2: Executar os testes e confirmar as falhas**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter="global_limit|other_company|limited_contacts|untabulated_ended|non_agents"
```

Esperado: pelo menos os cenarios de teto compartilhado e chamada encerrada sem tabulacao falham.

- [ ] **Step 3: Completar a elegibilidade e o consumo do lote**

Em `availableAgents()`, manter o bloqueio de chamadas em andamento e adicionar:

```php
->whereDoesntHave('calls', fn ($query) => $query
    ->where('status', 'ended')
    ->whereNull('disposition_id'))
```

Aplicar o mesmo criterio em `availableAgentsForCampaign()` para o calculo refletir os agentes que realmente podem receber lote.

Dentro da transacao, recalcular a capacidade apos adquirir o bloqueio. O valor retornado por `runCampaign()` deve ser o total de tentativas persistidas, vencedoras e canceladas.

- [ ] **Step 4: Executar todos os testes do discador**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php
```

Esperado: todos `PASS`.

- [ ] **Step 5: Commit**

```powershell
git add app/Services/Dialer/AgentCapacityService.php app/Services/Dialer/SimulatedDialerService.php tests/Feature/TenantAgentPanelTest.php
git commit -m "feat: enforce shared dialer concurrency limits"
```

### Task 5: Exibir uso contratado no Dashboard e Super Admin

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/SuperAdmin/CompanyController.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/super-admin/companies/index.blade.php`
- Test: `tests/Feature/CoreRoutesTest.php`
- Test: `tests/Feature/SuperAdminCompanyCrudTest.php`

- [ ] **Step 1: Escrever testes RED da interface**

No dashboard tenant, criar duas chamadas ativas e validar:

```php
$response
    ->assertSee('2 chamadas ativas')
    ->assertSee('Limite por operador: 3')
    ->assertSee('Teto da empresa: 10');
```

Na listagem de empresas, criar uma chamada ativa e validar:

```php
$response
    ->assertSee('1 ativa de 10')
    ->assertSee('3 por operador disponivel');
```

- [ ] **Step 2: Executar e confirmar as falhas**

```powershell
docker compose exec -T app php artisan test tests/Feature/CoreRoutesTest.php tests/Feature/SuperAdminCompanyCrudTest.php --filter="call_capacity|usage"
```

Esperado: `FAIL` por textos e dados ausentes.

- [ ] **Step 3: Fornecer os dados e atualizar as views**

Em `DashboardController`, calcular:

```php
'activeCallsCount' => $user->company_id
    ? Call::query()->where('company_id', $user->company_id)->where('status', 'in_progress')->count()
    : Call::query()->where('status', 'in_progress')->count(),
```

Em `CompanyController@index`, usar a relacao `calls()` ja existente e carregar:

```php
Company::query()
    ->with('license')
    ->withCount([
        'calls as active_calls_count' => fn ($query) => $query->where('status', 'in_progress'),
    ])
```

No dashboard substituir o card generico por:

```blade
<p class="text-3xl font-bold">{{ $activeCallsCount }} chamadas ativas</p>
<p class="mt-2 text-sm" style="color: var(--fg-muted);">
    Limite por operador: {{ $license?->max_calls_per_available_agent ?? '-' }}
    · Teto da empresa: {{ $license?->max_concurrent_calls ?? 'sem teto' }}
</p>
```

Na empresa mostrar:

```blade
{{ $company->active_calls_count }} ativa de {{ $company->license?->max_concurrent_calls ?? 'sem teto' }}
· {{ $company->license?->max_calls_per_available_agent ?? '-' }} por operador disponivel
```

- [ ] **Step 4: Executar os testes de interface**

```powershell
docker compose exec -T app php artisan test tests/Feature/CoreRoutesTest.php tests/Feature/SuperAdminCompanyCrudTest.php
```

Esperado: todos `PASS`.

- [ ] **Step 5: Commit**

```powershell
git add app/Http/Controllers/DashboardController.php app/Http/Controllers/SuperAdmin/CompanyController.php resources/views/dashboard.blade.php resources/views/super-admin/companies/index.blade.php tests/Feature/CoreRoutesTest.php tests/Feature/SuperAdminCompanyCrudTest.php
git commit -m "feat: show company call capacity usage"
```

### Task 6: Distinguir tentativas canceladas nos relatorios

**Files:**
- Modify: `app/Http/Controllers/Tenant/ReportController.php`
- Modify: `resources/views/tenant/reports/index.blade.php`
- Test: `tests/Feature/TenantReportsTest.php`

- [ ] **Step 1: Escrever o teste RED**

Criar uma chamada encerrada atendida com dois minutos e uma cancelada `answered_elsewhere` com dez minutos entre inicio e fim. Validar:

```php
$response
    ->assertSee('2 chamadas')
    ->assertSee('Cancelada - outra tentativa atendida')
    ->assertSee('TMA 2 min');
```

Validar que a cancelada nao altera conversao e nao aparece como chamada encerrada atendida.

- [ ] **Step 2: Executar e confirmar a falha**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantReportsTest.php --filter=cancelled_attempts
```

Esperado: `FAIL`, pois o motivo ainda nao e apresentado e TMA inclui qualquer chamada com `ended_at`.

- [ ] **Step 3: Ajustar metricas e apresentacao**

Em `timeMetrics()`, filtrar:

```php
->where('status', 'ended')
->whereNotNull('answered_at')
->whereNotNull('ended_at')
```

Em `withReportLabels()`, adicionar:

```php
$call->setAttribute(
    'status_label',
    $call->status === 'cancelled' && $call->end_reason === 'answered_elsewhere'
        ? 'Cancelada - outra tentativa atendida'
        : match ($call->status) {
            'in_progress' => 'Em andamento',
            'ended' => 'Encerrada',
            'cancelled' => 'Cancelada',
            default => ucfirst($call->status),
        },
);
```

Usar `status_label` na tabela analitica. Manter `total_calls` incluindo todas as tentativas; manter `ended_calls` contando somente `status = ended`; manter conversao baseada somente em chamadas `ended` tabuladas.

- [ ] **Step 4: Executar os testes de relatorios**

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantReportsTest.php
```

Esperado: todos `PASS`.

- [ ] **Step 5: Commit**

```powershell
git add app/Http/Controllers/Tenant/ReportController.php resources/views/tenant/reports/index.blade.php tests/Feature/TenantReportsTest.php
git commit -m "feat: report cancelled dialer attempts"
```

### Task 7: Atualizar roadmap e verificar o projeto completo

**Files:**
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Atualizar o roadmap**

Registrar em Plataforma SaaS:

```markdown
- [x] Limite de chamadas por agente aplicado em lotes reais do simulador.
- [x] Teto simultaneo compartilhado entre campanhas da empresa.
- [x] Indicadores de uso contratado versus chamadas ativas.
```

Registrar em Discagem Simulada:

```markdown
- [x] Tentativas concorrentes com uma chamada vencedora por agente.
- [x] Cancelamento auditavel das tentativas excedentes.
```

- [ ] **Step 2: Executar migracoes e testes focados**

```powershell
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test tests/Feature/SaaSDataModelTest.php tests/Feature/TenantAgentPanelTest.php tests/Feature/CoreRoutesTest.php tests/Feature/SuperAdminCompanyCrudTest.php tests/Feature/TenantReportsTest.php
```

Esperado: migracoes concluidas e todos os testes focados passando.

- [ ] **Step 3: Executar verificacao completa**

```powershell
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T node npm test
docker compose exec -T node npm run build
```

Esperado: zero falhas em testes, formatacao e build.

- [ ] **Step 4: Verificar o fluxo no navegador**

Abrir `http://localhost:8087` e validar:

1. Super Admin ve uso atual e limites na lista de empresas.
2. Dashboard tenant mostra chamadas ativas, limite por operador e teto da empresa.
3. Uma campanha com dois agentes e multiplicador tres cria seis tentativas.
4. Cada agente recebe somente uma chamada ativa.
5. Relatorio mostra quatro tentativas como canceladas por outra tentativa atendida.
6. Nao ha sobreposicao ou texto cortado em 1440x900 e 390x844.

- [ ] **Step 5: Commit final de documentacao**

```powershell
git add docs/ROADMAP_CONCLUSAO.md
git commit -m "docs: complete simulated concurrency milestone"
```
