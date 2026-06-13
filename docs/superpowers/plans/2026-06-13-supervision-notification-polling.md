# Supervision and Notification Polling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Atualizar supervisao e notificacoes a cada 10 segundos sem recarregar a pagina, com isolamento SaaS, pausa em aba oculta, prevencao de concorrencia e backoff.

**Architecture:** Controllers tenant retornarao fragmentos Blade com elementos raiz estaveis. Um controlador Alpine reutilizavel, implementado em `resources/js/polling.js`, buscara o HTML com `fetch`, substituira somente o conteudo das regioes e reinicializara os descendentes Alpine. O motor sera testado com o test runner nativo do Node e dependencias de navegador injetaveis.

**Tech Stack:** PHP 8.3, Laravel 13, Blade fragments, Alpine.js, JavaScript ES modules, Node test runner, PHPUnit, Vite, Docker Compose.

**HTTP Contracts:** `GET /tenant/supervision/poll` e `GET /tenant/notifications/poll`, ambos privados, sem cache e com intervalo-base de 10 segundos.

**Retry Contract:** backoff de 10, 20, 40 e no maximo 60 segundos (`60_000` ms), com parada permanente para respostas 401, 403, 404 e 419.

---

## File Structure

- Create: `app/Services/Supervision/SupervisionSnapshot.php`
  Consulta e formata agentes, metricas, filtros e alertas para pagina e polling.
- Create: `resources/views/tenant/supervision/_metrics.blade.php`
  Fragmento de indicadores operacionais.
- Create: `resources/views/tenant/supervision/_agents.blade.php`
  Fragmento da grade e modais dos agentes.
- Create: `resources/views/tenant/supervision/poll.blade.php`
  View de resposta com os dois fragmentos nomeados.
- Create: `resources/views/tenant/notifications/poll.blade.php`
  View de resposta com o fragmento do painel lateral.
- Create: `resources/js/polling.js`
  Motor de polling Alpine com timeout recursivo, visibilidade, abort e backoff.
- Create: `tests/Frontend/polling.test.js`
  Testes unitarios do motor no Node.
- Modify: `app/Http/Controllers/Tenant/SupervisionController.php`
  Reutilizacao do snapshot e endpoint `poll`.
- Modify: `app/Http/Controllers/Tenant/NotificationController.php`
  Endpoint `poll` somente de leitura.
- Modify: `resources/views/tenant/supervision/index.blade.php`
  Regioes atualizaveis e configuracao do controlador.
- Modify: `resources/views/components/ui/notification-panel.blade.php`
  Elemento raiz, estado lido/nao lido e configuracao global do polling.
- Modify: `resources/js/app.js`
  Registro de `pollingRegion` no Alpine.
- Modify: `routes/web.php`
  Rotas GET de polling.
- Modify: `package.json`
  Script `test:frontend`.
- Modify: `tests/Feature/TenantSupervisionTest.php`
  Contratos do endpoint de supervisao.
- Modify: `tests/Feature/NotificationTest.php`
  Contratos do endpoint de notificacoes.
- Create: `tests/Feature/PollingMarkupTest.php`
  Contrato do markup Alpine e ausencia de APIs proibidas.
- Modify: `docs/ROADMAP_CONCLUSAO.md`
  Conclusao do polling e proximo plano de producao.

### Task 1: Snapshot e Fragmentos da Supervisao

**Files:**
- Create: `app/Services/Supervision/SupervisionSnapshot.php`
- Create: `resources/views/tenant/supervision/_metrics.blade.php`
- Create: `resources/views/tenant/supervision/_agents.blade.php`
- Create: `resources/views/tenant/supervision/poll.blade.php`
- Modify: `app/Http/Controllers/Tenant/SupervisionController.php`
- Modify: `resources/views/tenant/supervision/index.blade.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/TenantSupervisionTest.php`

- [ ] **Step 1: Write failing supervision polling tests**

Adicionar:

```php
public function test_supervision_poll_returns_filtered_tenant_fragments_with_no_store_headers(): void
{
    [$supervisor, $company] = $this->tenantUser('supervisor');
    [$paused] = $this->tenantUser('agent', $company, 'Agente Polling');
    [$otherAgent] = $this->tenantUser('agent', null, 'Agente Externo Polling');
    $team = Team::create([
        'company_id' => $company->id,
        'name' => 'Equipe Polling',
        'slug' => 'equipe-polling',
        'status' => 'active',
    ]);
    $team->users()->attach($paused, [
        'company_id' => $company->id,
        'membership_type' => 'agent',
    ]);

    AgentStatus::create([
        'company_id' => $company->id,
        'user_id' => $paused->id,
        'status' => 'paused',
        'started_at' => now()->subMinutes(25),
    ]);
    AgentStatus::create([
        'company_id' => $otherAgent->company_id,
        'user_id' => $otherAgent->id,
        'status' => 'paused',
        'started_at' => now(),
    ]);

    $this->actingAs($supervisor)
        ->get(route('tenant.supervision.poll', [
            'status' => 'paused',
            'team_id' => $team->id,
        ]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Poll-Interval', '10')
        ->assertSee('id="supervision-metrics"', false)
        ->assertSee('id="supervision-agents"', false)
        ->assertSee('Agente Polling')
        ->assertSee('Pausa longa')
        ->assertDontSee('Agente Externo Polling')
        ->assertDontSee('<html', false);
}
```

Adicionar teste de equipe externa:

```php
public function test_supervision_poll_rejects_team_from_another_company(): void
{
    [$supervisor] = $this->tenantUser('supervisor');
    [, $otherCompany] = $this->tenantUser('agent');
    $otherTeam = Team::create([
        'company_id' => $otherCompany->id,
        'name' => 'Equipe Externa',
        'slug' => 'equipe-externa',
        'status' => 'active',
    ]);

    $this->actingAs($supervisor)
        ->get(route('tenant.supervision.poll', ['team_id' => $otherTeam->id]))
        ->assertNotFound();
}
```

Atualizar o teste de alertas para chamar `tenant.supervision.poll` duas vezes e continuar esperando uma notificacao de cada tipo.

- [ ] **Step 2: Run tests and verify RED**

```powershell
php artisan test --filter=TenantSupervisionTest --no-coverage
```

Expected: FAIL porque a rota `tenant.supervision.poll` ainda nao existe.

- [ ] **Step 3: Extract `SupervisionSnapshot`**

Criar:

```php
final class SupervisionSnapshot
{
    public function __construct(
        private readonly AppNotificationService $notifications
    ) {}

    public function forCompany(int $companyId, string $status = '', ?int $teamId = null): array
    {
        if ($teamId !== null) {
            Team::query()
                ->where('company_id', $companyId)
                ->findOrFail($teamId);
        }

        $allowedStatuses = ['', 'available', 'paused', 'in_call', 'offline'];
        abort_unless(in_array($status, $allowedStatuses, true), 404);

        $agents = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with([
                'currentAgentStatus',
                'calls' => fn ($query) => $query
                    ->where('status', 'in_progress')
                    ->with('campaign')
                    ->latest(),
            ])
            ->when($teamId, fn ($query) => $query->whereIn(
                'users.id',
                fn ($teamQuery) => $teamQuery
                    ->select('team_user.user_id')
                    ->from('team_user')
                    ->where('team_user.company_id', $companyId)
                    ->where('team_user.team_id', $teamId)
            ))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($companyId): User {
                $activeCall = $user->calls->first();
                $statusStartedAt = $user->currentAgentStatus?->started_at;
                $statusMinutes = $statusStartedAt
                    ? (int) $statusStartedAt->diffInMinutes(now())
                    : 0;
                $callMinutes = $activeCall?->started_at
                    ? (int) $activeCall->started_at->diffInMinutes(now())
                    : 0;

                $user->setAttribute(
                    'supervision_status',
                    $user->currentAgentStatus?->status ?? 'offline'
                );
                $user->setAttribute(
                    'status_duration_label',
                    $this->durationLabel($statusMinutes)
                );
                $user->setAttribute(
                    'active_call_duration_label',
                    $activeCall ? $this->durationLabel($callMinutes) : null
                );
                $user->setAttribute(
                    'current_campaign_name',
                    $activeCall?->campaign?->name
                );
                $user->setAttribute('operational_alerts', collect([
                    $user->currentAgentStatus?->status === 'paused'
                        && $statusMinutes >= 20 ? 'Pausa longa' : null,
                    $activeCall && $callMinutes >= 15
                        ? 'Chamada longa' : null,
                ])->filter()->values()->all());

                $this->notifyOperationalAlerts(
                    $user,
                    $statusMinutes,
                    $callMinutes,
                    $activeCall
                );

                $user->setAttribute('team_names', DB::table('team_user')
                    ->join('teams', 'teams.id', '=', 'team_user.team_id')
                    ->where('team_user.company_id', $companyId)
                    ->where('team_user.user_id', $user->id)
                    ->orderBy('teams.name')
                    ->pluck('teams.name')
                    ->all());

                return $user;
            })
            ->when(
                $status !== '',
                fn ($collection) => $collection->filter(
                    fn (User $user): bool =>
                        $user->supervision_status === $status
                )
            )
            ->values();

        $statuses = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with('currentAgentStatus')
            ->get()
            ->map(fn (User $user): string =>
                $user->currentAgentStatus?->status ?? 'offline'
            );

        $metrics = [
            'available' => $statuses->filter(
                fn (string $value): bool => $value === 'available'
            )->count(),
            'paused' => $statuses->filter(
                fn (string $value): bool => $value === 'paused'
            )->count(),
            'in_call' => $statuses->filter(
                fn (string $value): bool => $value === 'in_call'
            )->count(),
            'offline' => $statuses->filter(
                fn (string $value): bool => $value === 'offline'
            )->count(),
            'active_calls' => Call::query()
                ->where('company_id', $companyId)
                ->where('status', 'in_progress')
                ->count(),
        ];

        return [
            'agents' => $agents,
            'teams' => Team::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'selectedStatus' => $status,
            'selectedTeamId' => $teamId,
            'metrics' => $metrics,
        ];
    }
}
```

Implementar `durationLabel()` com o formato atual em minutos/horas. Implementar `notifyOperationalAlerts()` com as duas chamadas atuais a `createForCompanyAudience`, mantendo os titulos `Pausa longa` e `Chamada sem tabulacao`, audiencia `company_admin`/`supervisor`, severidade `warning`, rota `tenant.supervision.index` e `dedupe: true`.

- [ ] **Step 4: Add fragments and controller endpoint**

O controller fica:

```php
class SupervisionController extends Controller
{
    public function __construct(
        private readonly SupervisionSnapshot $snapshot
    ) {}

    public function index(Request $request): View
    {
        return view(
            'tenant.supervision.index',
            $this->snapshot->forCompany(
                $request->user()->company_id,
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            )
        );
    }

    public function poll(Request $request): Response
    {
        $html = view(
            'tenant.supervision.poll',
            $this->snapshot->forCompany(
                $request->user()->company_id,
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            )
        )->fragments(['supervision-metrics', 'supervision-agents']);

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Poll-Interval', '10');
    }
}
```

Importar `Symfony\Component\HttpFoundation\Response` no controller.

Adicionar antes da rota principal:

```php
Route::get('supervision/poll', [SupervisionController::class, 'poll'])
    ->name('supervision.poll');
```

`poll.blade.php`:

```blade
@fragment('supervision-metrics')
    @include('tenant.supervision._metrics')
@endfragment

@fragment('supervision-agents')
    @include('tenant.supervision._agents')
@endfragment
```

Copiar os cinco cards de metricas para `_metrics.blade.php`. Copiar a listagem `@forelse ($agents as $agent)`, incluindo botoes e modais, para `_agents.blade.php`. Os elementos raiz devem ser:

```blade
<div id="supervision-metrics" class="grid gap-3 md:grid-cols-5">
```

e:

```blade
<div id="supervision-agents" class="mt-5 grid gap-3">
```

- [ ] **Step 5: Run supervision tests and verify GREEN**

```powershell
php artisan test --filter=TenantSupervisionTest --no-coverage
```

Expected: todos os testes passam, incluindo isolamento, filtros e deduplicacao.

- [ ] **Step 6: Commit supervision fragments**

```powershell
git add app/Services/Supervision/SupervisionSnapshot.php app/Http/Controllers/Tenant/SupervisionController.php resources/views/tenant/supervision/_metrics.blade.php resources/views/tenant/supervision/_agents.blade.php resources/views/tenant/supervision/poll.blade.php resources/views/tenant/supervision/index.blade.php routes/web.php tests/Feature/TenantSupervisionTest.php
git commit -m "feat: add supervision polling fragments"
```

### Task 2: Fragmento de Notificacoes

**Files:**
- Create: `resources/views/tenant/notifications/poll.blade.php`
- Modify: `app/Http/Controllers/Tenant/NotificationController.php`
- Modify: `resources/views/components/ui/notification-panel.blade.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/NotificationTest.php`

- [ ] **Step 1: Write failing notification polling test**

Adicionar:

```php
public function test_notification_poll_returns_only_current_user_panel_and_unread_state(): void
{
    $company = $this->company();
    $user = $this->tenantUser($company, 'company_admin', '1001');
    $otherUser = $this->tenantUser($company, 'supervisor', '1002');

    $read = app(AppNotificationService::class)->createForUser(
        $user, 'system', 'info', 'Aviso lido', 'Mensagem lida'
    );
    $read->update(['read_at' => now()]);
    app(AppNotificationService::class)->createForUser(
        $user, 'system', 'warning', 'Aviso novo', 'Mensagem nova'
    );
    app(AppNotificationService::class)->createForUser(
        $otherUser, 'system', 'info', 'Aviso externo', 'Nao mostrar'
    );

    $this->actingAs($user)
        ->get(route('tenant.notifications.poll'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Poll-Interval', '10')
        ->assertSee('id="notification-panel"', false)
        ->assertSee('data-unread-count="1"', false)
        ->assertSee('Aviso novo')
        ->assertSee('Aviso lido')
        ->assertSee('data-notification-state="unread"', false)
        ->assertSee('data-notification-state="read"', false)
        ->assertDontSee('Aviso externo')
        ->assertDontSee('<html', false);
}
```

- [ ] **Step 2: Run test and verify RED**

```powershell
php artisan test --filter=NotificationTest::test_notification_poll_returns_only_current_user_panel_and_unread_state --no-coverage
```

Expected: FAIL porque a rota nao existe.

- [ ] **Step 3: Implement controller and fragment**

Adicionar ao controller:

```php
public function poll(Request $request): Response
{
    $notifications = $request->user()
        ->appNotifications()
        ->latest()
        ->limit(8)
        ->get();
    $unreadCount = $request->user()
        ->appNotifications()
        ->whereNull('read_at')
        ->count();

    $html = view('tenant.notifications.poll', compact(
        'notifications',
        'unreadCount'
    ))->fragment('notification-panel');

    return response($html)
        ->header('Cache-Control', 'no-store, private')
        ->header('X-Poll-Interval', '10');
}
```

Adicionar:

```php
Route::get('notifications/poll', [NotificationController::class, 'poll'])
    ->name('notifications.poll');
```

View:

```blade
@fragment('notification-panel')
    <x-ui.notification-panel
        :notifications="$notifications"
        :unread-count="$unreadCount"
    />
@endfragment
```

No componente, adicionar:

```blade
<aside
    id="notification-panel"
    data-unread-count="{{ $unreadCount }}"
    ...
>
```

Cada item deve possuir:

```blade
data-notification-state="{{ $notification->read_at ? 'read' : 'unread' }}"
```

e fundo visual mais forte apenas para nao lidas.

- [ ] **Step 4: Run notification suite and verify GREEN**

```powershell
php artisan test --filter=NotificationTest --no-coverage
```

Expected: todos os testes passam.

- [ ] **Step 5: Commit notification fragment**

```powershell
git add app/Http/Controllers/Tenant/NotificationController.php resources/views/tenant/notifications/poll.blade.php resources/views/components/ui/notification-panel.blade.php routes/web.php tests/Feature/NotificationTest.php
git commit -m "feat: add notification polling fragment"
```

### Task 3: Motor JavaScript de Polling

**Files:**
- Create: `resources/js/polling.js`
- Create: `tests/Frontend/polling.test.js`
- Modify: `resources/js/app.js`
- Modify: `package.json`

- [ ] **Step 1: Add Node test script and failing unit tests**

Em `package.json`:

```json
"scripts": {
    "build": "vite build",
    "dev": "vite",
    "test:frontend": "node --test tests/Frontend/*.test.js"
}
```

O teste importa:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { createPollingRegion } from '../../resources/js/polling.js';
```

Criar dependencias falsas controlaveis:

```js
function harness(overrides = {}) {
    const scheduled = [];
    const cleared = [];
    let visibilityState = 'visible';

    return {
        scheduled,
        cleared,
        setVisibility(value) {
            visibilityState = value;
        },
        dependencies: {
            fetch: overrides.fetch ?? (async () => ({
                ok: true,
                status: 200,
                headers: { get: () => '10' },
                text: async () => '<div id="region">novo</div>',
            })),
            parse: overrides.parse ?? (() => new Map([
                ['#region', '<span>novo</span>'],
            ])),
            replace: overrides.replace ?? (() => {}),
            setTimeout: (callback, delay) => {
                scheduled.push({ callback, delay });
                return scheduled.length;
            },
            clearTimeout: (id) => cleared.push(id),
            visibility: () => visibilityState,
            warn: () => {},
            AbortController,
        },
    };
}
```

Testes obrigatorios:

```js
test('starts with one delayed poll and never uses overlapping requests', async () => {});
test('pauses while hidden and polls immediately when visible again', async () => {});
test('backs off after temporary failure and resets after success', async () => {});
test('stops permanently on authentication or authorization errors', async () => {});
test('aborts active request when destroyed', async () => {});
```

Assercoes:

- primeiro timeout em `10000`;
- segunda chamada ignorada enquanto promise da primeira nao resolveu;
- aba oculta nao chama fetch;
- retorno visivel chama `poll()` imediatamente;
- falhas usam `20000`, depois `40000`, limitadas a `60000`;
- sucesso volta a `10000`;
- 401, 403, 404 e 419 definem `stopped = true`;
- `destroy()` chama `abort()` e limpa timer.

- [ ] **Step 2: Run tests and verify RED**

```powershell
npm run test:frontend
```

Expected: FAIL porque `resources/js/polling.js` nao existe.

- [ ] **Step 3: Implement dependency-injected polling engine**

Exportar:

```js
export function createPollingRegion(config, dependencies = browserDependencies()) {
    return {
        url: config.url,
        selectors: config.selectors,
        intervalMs: (config.intervalSeconds ?? 10) * 1000,
        currentDelayMs: (config.intervalSeconds ?? 10) * 1000,
        maxDelayMs: 60000,
        timerId: null,
        controller: null,
        inFlight: false,
        stopped: false,

        start() {
            this.schedule(this.intervalMs);
        },

        schedule(delay) {
            if (this.stopped || dependencies.visibility() !== 'visible') {
                return;
            }

            this.clearTimer();
            this.timerId = dependencies.setTimeout(() => this.poll(), delay);
        },

        async poll() {
            if (
                this.stopped ||
                this.inFlight ||
                dependencies.visibility() !== 'visible'
            ) {
                return;
            }

            this.inFlight = true;
            this.controller = new dependencies.AbortController();

            try {
                const response = await dependencies.fetch(this.url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: this.controller.signal,
                });

                if ([401, 403, 404, 419].includes(response.status)) {
                    this.stopped = true;
                    return;
                }

                if (!response.ok) {
                    throw new Error(`Polling failed with ${response.status}`);
                }

                const regions = dependencies.parse(
                    await response.text(),
                    this.selectors,
                );
                dependencies.replace(regions);
                const headerSeconds = Number(response.headers.get('X-Poll-Interval'));
                this.intervalMs = Number.isFinite(headerSeconds) && headerSeconds > 0
                    ? headerSeconds * 1000
                    : this.intervalMs;
                this.currentDelayMs = this.intervalMs;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    dependencies.warn(error);
                    this.currentDelayMs = Math.min(
                        Math.max(this.currentDelayMs * 2, 20000),
                        this.maxDelayMs,
                    );
                }
            } finally {
                this.inFlight = false;
                this.controller = null;
                this.schedule(this.currentDelayMs);
            }
        },

        handleVisibilityChange() {
            if (dependencies.visibility() !== 'visible') {
                this.clearTimer();
                return;
            }

            void this.poll();
        },

        clearTimer() {
            if (this.timerId !== null) {
                dependencies.clearTimeout(this.timerId);
                this.timerId = null;
            }
        },

        destroy() {
            this.stopped = true;
            this.clearTimer();
            this.controller?.abort();
        },
    };
}
```

`browserDependencies()` deve:

- usar `window.fetch`;
- usar `window.setTimeout` e `window.clearTimeout`;
- retornar `document.visibilityState`;
- analisar com `DOMParser`;
- validar todos os seletores antes de substituir;
- chamar `window.Alpine.destroyTree(target)` antes de mudar `innerHTML`;
- chamar `window.Alpine.initTree(target)` depois;
- usar `console.warn` somente quando `import.meta.env.DEV`.

Exportar para Alpine:

```js
export function pollingRegion(config) {
    return createPollingRegion(config);
}
```

- [ ] **Step 4: Register module in Alpine**

`resources/js/app.js`:

```js
import Alpine from 'alpinejs';
import { toastStack } from './notifications';
import { pollingRegion } from './polling';

window.Alpine = Alpine;
window.toastStack = toastStack;
window.pollingRegion = pollingRegion;

Alpine.start();
```

- [ ] **Step 5: Run frontend tests and build**

```powershell
npm run test:frontend
npm run build
```

Expected: cinco testes Node passam e Vite conclui.

- [ ] **Step 6: Commit polling engine**

```powershell
git add resources/js/polling.js resources/js/app.js tests/Frontend/polling.test.js package.json
git commit -m "feat: add resilient polling engine"
```

### Task 4: Integracao Alpine, Markup e Roadmap

**Files:**
- Modify: `resources/views/tenant/supervision/index.blade.php`
- Modify: `resources/views/components/ui/notification-panel.blade.php`
- Create: `tests/Feature/PollingMarkupTest.php`
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Write failing markup tests**

Criar teste que autentica um supervisor e verifica na supervisao:

```php
->assertSee('pollingRegion(', false)
->assertSee(route('tenant.supervision.poll'), false)
->assertSee("'#supervision-metrics'", false)
->assertSee("'#supervision-agents'", false)
->assertSee('x-init="start()"', false)
->assertSee('x-on:visibilitychange.document="handleVisibilityChange()"', false);
```

Verificar em dashboard autenticado:

```php
->assertSee('id="notification-panel"', false)
->assertSee(route('tenant.notifications.poll'), false)
->assertSee("'#notification-panel'", false);
```

Ler `resources/js/polling.js` e afirmar:

```php
$source = file_get_contents(resource_path('js/polling.js'));
$this->assertStringContainsString('AbortController', $source);
$this->assertStringContainsString('visibility', $source);
$this->assertStringNotContainsString('setInterval(', $source);
$this->assertStringNotContainsString('alert(', $source);
$this->assertStringNotContainsString('confirm(', $source);
```

- [ ] **Step 2: Run markup test and verify RED**

```powershell
php artisan test --filter=PollingMarkupTest --no-coverage
```

Expected: FAIL porque as regioes ainda nao possuem o controlador Alpine.

- [ ] **Step 3: Integrate supervision polling**

No `index.blade.php`, envolver metricas e card operacional:

```blade
<div
    class="grid gap-4"
    x-data="pollingRegion({
        url: @js(route('tenant.supervision.poll', array_filter([
            'status' => $selectedStatus,
            'team_id' => $selectedTeamId,
        ]))),
        selectors: ['#supervision-metrics', '#supervision-agents'],
        intervalSeconds: 10,
    })"
    x-init="start()"
    x-on:visibilitychange.document="handleVisibilityChange()"
>
```

Manter filtros fora dos elementos substituidos. Incluir `_metrics` e `_agents` nos locais atuais.

- [ ] **Step 4: Integrate notification polling**

No elemento raiz do componente:

```blade
x-data="pollingRegion({
    url: @js(route('tenant.notifications.poll')),
    selectors: ['#notification-panel'],
    intervalSeconds: 10,
})"
x-init="start()"
x-on:visibilitychange.document="handleVisibilityChange()"
```

Como o cliente preserva o elemento raiz e troca somente `innerHTML`, o controlador permanece ativo durante atualizacoes.

- [ ] **Step 5: Run focused suites**

```powershell
php artisan test --filter=PollingMarkupTest --no-coverage
php artisan test --filter=TenantSupervisionTest --no-coverage
php artisan test --filter=NotificationTest --no-coverage
npm run test:frontend
```

Expected: todas passam.

- [ ] **Step 6: Update roadmap**

Atualizar:

```markdown
- [x] Atualizacao por polling leve.
- [x] Fallback por polling para notificacoes.
```

Manter Reverb/Echo como evolucao futura. Alterar o proximo plano recomendado para:

```text
Preparacao para Producao
```

Entregaveis:

- imagem Docker imutavel;
- compose de producao sem bind mounts;
- segredos e cookies seguros;
- HTTPS, health checks e restart policies;
- backup/restore do MariaDB;
- logs, monitoramento e runbook de deploy.

- [ ] **Step 7: Run full local verification**

```powershell
vendor\bin\pint.bat --dirty
php artisan test --no-coverage
npm run test:frontend
npm run build
git diff --check
```

Expected: Pint passa, suite PHP completa passa, testes Node passam, build conclui e diff-check fica vazio.

- [ ] **Step 8: Run Docker verification and smoke**

```powershell
docker compose exec -T app php artisan test --no-coverage
```

Smoke:

```powershell
$response = Invoke-WebRequest -UseBasicParsing http://localhost:8087/login
"STATUS=$($response.StatusCode)"
"TITLE_OK=$([int]($response.Content -match 'Discador THCONECT'))"
```

Expected: suite Docker passa, `STATUS=200` e `TITLE_OK=1`.

- [ ] **Step 9: Commit integration and roadmap**

```powershell
git add resources/views/tenant/supervision/index.blade.php resources/views/components/ui/notification-panel.blade.php tests/Feature/PollingMarkupTest.php docs/ROADMAP_CONCLUSAO.md
git add -u
git commit -m "feat: enable supervision notification polling"
```

- [ ] **Step 10: Confirm final state**

```powershell
git status --short
git log -6 --oneline
```

Expected: arvore limpa e commits do polling visiveis.
