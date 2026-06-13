# Tenant RBAC Enforcement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplicar autorizacao real por perfil e tipo de usuario em todas as rotas, comandos e itens de navegacao do tenant.

**Architecture:** A `PermissionMatrix` continuara sendo a fonte unica das chaves. `User::hasPermission()` aplicara primeiro os limites estruturais do tipo de usuario e depois as permissoes do perfil, com acesso integral apenas para um perfil administrativo da mesma empresa. As rotas usarao Gates via middleware `can:` e as views usarao `@can`, mantendo o servidor como autoridade final.

**Tech Stack:** PHP 8.3, Laravel 13, Blade, PHPUnit 12, MySQL, Docker Compose.

---

## File Structure

- Modify: `app/Support/Tenant/PermissionMatrix.php` - registrar todos os modulos e operacoes.
- Modify: `app/Models/User.php` - aplicar limites por tipo, administrador do tenant e isolamento do perfil.
- Modify: `app/Providers/AppServiceProvider.php` - manter os Gates derivados da matriz.
- Modify: `routes/web.php` - atribuir uma permissao a cada rota funcional do tenant.
- Modify: `database/seeders/DemoTenantSeeder.php` - criar perfis administrativos, de supervisor e de agente distintos.
- Modify: `resources/views/layouts/app.blade.php` - filtrar a sidebar por Gate.
- Modify: `resources/views/tenant/*/index.blade.php` - ocultar comandos sem permissao.
- Modify: `resources/views/tenant/settings/partials/*.blade.php` - renderizar formularios apenas dentro dos blocos autorizados.
- Modify: `tests/Feature/RbacSeederTest.php` - validar matriz, Gates e perfis padrao.
- Create: `tests/Feature/TenantRbacAuthorizationTest.php` - validar acesso HTTP por perfil e operacao.
- Create: `tests/Feature/TenantRbacNavigationTest.php` - validar sidebar e comandos visiveis.

### Task 1: Expandir a matriz e o nucleo de autorizacao

**Files:**
- Modify: `tests/Feature/RbacSeederTest.php`
- Modify: `app/Support/Tenant/PermissionMatrix.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Escrever testes falhando para as novas chaves e regras estruturais**

Adicionar testes que exijam:

```php
$this->assertContains('campaigns.operate', PermissionMatrix::keys());
$this->assertContains('agent.access', PermissionMatrix::keys());
$this->assertContains('reports.export', PermissionMatrix::keys());
$this->assertContains('reports.schedules', PermissionMatrix::keys());
$this->assertContains('settings.delete', PermissionMatrix::keys());

$this->assertTrue(Gate::forUser($tenantAdmin)->allows('tenant.settings.delete'));
$this->assertFalse(Gate::forUser($agent)->allows('tenant.users.read'));
$this->assertTrue(Gate::forUser($agent)->allows('tenant.agent.access'));
$this->assertFalse(Gate::forUser($supervisor)->allows('tenant.agent.access'));
$this->assertFalse(Gate::forUser($superAdmin)->allows('tenant.users.read'));
```

Criar ainda um perfil administrativo pertencente a outra empresa e confirmar que ele nao concede permissao ao usuario.

- [ ] **Step 2: Executar o teste e confirmar a falha esperada**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/RbacSeederTest.php
```

Expected: FAIL porque as novas chaves nao existem e `is_admin` ainda nao concede acesso integral.

- [ ] **Step 3: Ampliar `PermissionMatrix::modules()`**

Registrar exatamente as chaves aprovadas na especificacao:

```php
'lists' => ['label' => 'Listas', 'permissions' => self::crud('lists')],
'campaigns' => [
    'label' => 'Campanhas',
    'permissions' => [
        ...self::crud('campaigns'),
        'operate' => ['key' => 'campaigns.operate', 'label' => 'Operar'],
    ],
],
'agent' => ['label' => 'Agente', 'permissions' => self::actions('agent', [
    'access' => 'Acessar',
])],
'supervision' => ['label' => 'Supervisao', 'permissions' => self::onlyRead('supervision')],
'reports' => ['label' => 'Relatorios', 'permissions' => self::actions('reports', [
    'read' => 'Ler',
    'export' => 'Exportar',
    'schedules' => 'Agendar',
])],
'telephony' => ['label' => 'Telefonia', 'permissions' => self::onlyRead('telephony')],
'settings' => ['label' => 'Configuracoes', 'permissions' => self::crud('settings')],
'notifications' => ['label' => 'Notificacoes', 'permissions' => self::actions('notifications', [
    'read' => 'Ler',
    'update' => 'Marcar como lida',
])],
```

Adicionar um helper privado `actions(string $module, array $actions)` que transforme cada par em `['key' => "{$module}.{$action}", 'label' => $label]`, sem alterar o formato consumido pelas views e pelo seeder.

- [ ] **Step 4: Implementar as regras em `User::hasPermission()`**

Aplicar esta ordem:

```php
if ($this->is_super_admin || ! $this->company_id) {
    return false;
}

$role = $this->role;

if (! $role || $role->company_id !== $this->company_id) {
    return false;
}

if ($role->is_admin) {
    return true;
}

if ($this->user_type === 'agent' && ! in_array($permissionKey, [
    'dashboard.read',
    'agent.access',
    'notifications.read',
    'notifications.update',
], true)) {
    return false;
}

if ($this->user_type === 'supervisor' && $permissionKey === 'agent.access') {
    return false;
}

$role->loadMissing('permissions');

return $role->permissions->contains('key', $permissionKey);
```

O tipo `company_admin` sem perfil administrativo continua obedecendo as permissoes normais do perfil.

- [ ] **Step 5: Executar os testes do nucleo**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/RbacSeederTest.php tests/Feature/SaaSDataModelTest.php
```

Expected: PASS.

- [ ] **Step 6: Commitar o nucleo**

```powershell
git add app/Support/Tenant/PermissionMatrix.php app/Models/User.php tests/Feature/RbacSeederTest.php
git commit -m "feat: enforce tenant permission rules"
```

### Task 2: Proteger todas as rotas do tenant

**Files:**
- Create: `tests/Feature/TenantRbacAuthorizationTest.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Criar helpers de teste para usuarios autorizados**

No novo teste, criar empresa com licenca valida, perfil e usuario:

```php
private function tenantUser(
    string $userType,
    array $permissions = [],
    bool $isAdmin = false,
): User {
    $company = Company::create(['name' => fake()->company()]);
    $company->licenses()->create([
        'status' => 'active',
        'starts_at' => now()->subDay()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
    ]);

    $this->seed(PermissionSeeder::class);

    $role = $company->roles()->create([
        'name' => fake()->unique()->jobTitle(),
        'slug' => fake()->unique()->slug(),
        'is_admin' => $isAdmin,
    ]);
    $role->permissions()->sync(
        Permission::whereIn('key', $permissions)->pluck('id')
    );

    return User::factory()->create([
        'company_id' => $company->id,
        'role_id' => $role->id,
        'user_type' => $userType,
        'status' => 'active',
        'extension' => fake()->unique()->numerify('1###'),
        'email_verified_at' => now(),
    ]);
}
```

- [ ] **Step 2: Escrever testes falhando para leitura e CRUD**

Cobrir pelo menos:

```php
$this->actingAs($agent)->get(route('tenant.users.index'))->assertForbidden();
$this->actingAs($reader)->get(route('tenant.users.index'))->assertOk();
$this->actingAs($reader)->post(route('tenant.users.store'), $payload)->assertForbidden();
$this->actingAs($creator)->post(route('tenant.users.store'), $payload)->assertRedirect();
$this->actingAs($updater)->put(route('tenant.users.update', $target), $payload)->assertRedirect();
$this->actingAs($deleter)->delete(route('tenant.users.destroy', $target))->assertRedirect();
```

Adicionar casos equivalentes para listas, equipes, perfis, configuracoes e campanhas.

- [ ] **Step 3: Escrever testes falhando para operacoes especiais**

Validar separadamente:

```php
$this->actingAs($campaignReader)
    ->patch(route('tenant.campaigns.start', $campaign))
    ->assertForbidden();

$this->actingAs($campaignOperator)
    ->patch(route('tenant.campaigns.start', $campaign))
    ->assertRedirect();

$this->actingAs($reportReader)
    ->get(route('tenant.reports.export'))
    ->assertForbidden();

$this->actingAs($reportExporter)
    ->get(route('tenant.reports.export'))
    ->assertOk();

$this->actingAs($reportReader)
    ->post(route('tenant.reports.schedules.store'), $schedulePayload)
    ->assertForbidden();
```

Cobrir `supervision.poll`, `notifications.poll`, marcacao de notificacoes, todas as exportacoes e todas as acoes de campanha.

Adicionar tambem:

```php
$this->actingAs($tenantWithoutDashboardPermission)
    ->get(route('dashboard'))
    ->assertForbidden();

$this->actingAs($superAdmin)
    ->get(route('dashboard'))
    ->assertOk();
```

- [ ] **Step 4: Executar o novo teste e confirmar que as rotas ainda permitem acesso**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantRbacAuthorizationTest.php
```

Expected: FAIL porque as rotas ainda estao protegidas apenas pelos middlewares gerais do tenant.

- [ ] **Step 5: Agrupar as rotas por permissao**

Em `routes/web.php`, aplicar `can:` por operacao. O padrao para resources sera:

```php
Route::get('users', [UserController::class, 'index'])
    ->middleware('can:tenant.users.read')
    ->name('users.index');
Route::post('users', [UserController::class, 'store'])
    ->middleware('can:tenant.users.create')
    ->name('users.store');
Route::put('users/{user}', [UserController::class, 'update'])
    ->middleware('can:tenant.users.update')
    ->name('users.update');
Route::delete('users/{user}', [UserController::class, 'destroy'])
    ->middleware('can:tenant.users.delete')
    ->name('users.destroy');
```

Aplicar o mesmo mapeamento a equipes, perfis, listas e campanhas. Usar:

- `campaigns.operate`: start, pause, restart, recycle e dial;
- `campaigns.create`: store e duplicate;
- `reports.read`: index;
- `reports.export`: CSV, XLSX e PDF;
- `reports.schedules`: store, update, status e run;
- `agent.access`: index, status, pause, storeCall e endCall;
- `supervision.read`: index e poll;
- `settings.read/create/update/delete`: conforme o verbo;
- `notifications.read`: poll;
- `notifications.update`: read e read-all;
- `telephony.read`: tela "Em breve" de telefonia;
- `integrations.read`: tela "Em breve" de integracoes.

Como o dashboard e compartilhado entre o painel global e o tenant, manter a rota comum e autorizar no controller:

```php
if (! $user->is_super_admin) {
    Gate::authorize('tenant.dashboard.read');
}
```

- [ ] **Step 6: Executar os testes de autorizacao**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantRbacAuthorizationTest.php
```

Expected: PASS.

- [ ] **Step 7: Executar testes funcionais afetados**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantUserCrudTest.php tests/Feature/TenantTeamRoleCrudTest.php tests/Feature/TenantCampaignListCrudTest.php tests/Feature/TenantAgentPanelTest.php tests/Feature/TenantSupervisionTest.php tests/Feature/TenantReportsTest.php tests/Feature/TenantReportScheduleTest.php tests/Feature/TenantSettingsTest.php tests/Feature/NotificationTest.php
```

Expected: PASS. Caso um fixture antigo nao possua perfil administrativo, corrigir somente o helper do teste para representar o usuario administrativo que o teste ja pretendia criar.

- [ ] **Step 8: Commitar as rotas protegidas**

```powershell
git add app/Http/Controllers/DashboardController.php routes/web.php tests/Feature/TenantRbacAuthorizationTest.php tests/Feature
git commit -m "feat: protect tenant routes by permission"
```

### Task 3: Corrigir os perfis padrao do ambiente de desenvolvimento

**Files:**
- Modify: `tests/Feature/RbacSeederTest.php`
- Modify: `database/seeders/DemoTenantSeeder.php`

- [ ] **Step 1: Escrever teste falhando para os tres perfis**

Exigir:

```php
$adminRole = $company->roles()->where('slug', 'administrador')->sole();
$supervisorRole = $company->roles()->where('slug', 'supervisor')->sole();
$agentRole = $company->roles()->where('slug', 'agente')->sole();

$this->assertTrue($adminRole->is_admin);
$this->assertFalse($supervisorRole->is_admin);
$this->assertFalse($agentRole->is_admin);
$this->assertTrue($supervisorRole->permissions->contains('key', 'supervision.read'));
$this->assertFalse($supervisorRole->permissions->contains('key', 'agent.access'));
$this->assertTrue($agentRole->permissions->contains('key', 'agent.access'));
$this->assertSame($supervisorRole->id, $supervisor->role_id);
$this->assertSame($agentRole->id, $agentOne->role_id);
```

- [ ] **Step 2: Executar e confirmar a falha**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/RbacSeederTest.php
```

Expected: FAIL porque todos os usuarios demo ainda recebem o perfil administrador.

- [ ] **Step 3: Criar e sincronizar os perfis padrao**

Manter o administrador com todas as chaves. Criar:

```php
$supervisorRole = $this->role($company, 'Supervisor', 'supervisor', [
    'dashboard.read',
    'teams.read',
    'lists.read',
    'campaigns.read',
    'campaigns.operate',
    'supervision.read',
    'reports.read',
    'reports.export',
    'reports.schedules',
    'notifications.read',
    'notifications.update',
]);

$agentRole = $this->role($company, 'Agente', 'agente', [
    'dashboard.read',
    'agent.access',
    'notifications.read',
    'notifications.update',
]);
```

Associar cada usuario ao perfil correto e manter as equipes atuais.

- [ ] **Step 4: Executar o seeder e os testes**

Run:

```powershell
docker compose exec -T app php artisan db:seed --class=PermissionSeeder
docker compose exec -T app php artisan db:seed --class=DemoTenantSeeder
docker compose exec -T app php artisan test tests/Feature/RbacSeederTest.php
```

Expected: comandos e testes aprovados, sem duplicar perfis.

- [ ] **Step 5: Commitar os perfis padrao**

```powershell
git add database/seeders/DemoTenantSeeder.php tests/Feature/RbacSeederTest.php
git commit -m "feat: seed tenant roles by user type"
```

### Task 4: Filtrar sidebar e comandos das telas

**Files:**
- Create: `tests/Feature/TenantRbacNavigationTest.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/tenant/users/index.blade.php`
- Modify: `resources/views/tenant/teams/index.blade.php`
- Modify: `resources/views/tenant/roles/index.blade.php`
- Modify: `resources/views/tenant/lists/index.blade.php`
- Modify: `resources/views/tenant/campaigns/index.blade.php`
- Modify: `resources/views/tenant/reports/index.blade.php`
- Modify: `resources/views/tenant/settings/index.blade.php`
- Modify: `resources/views/tenant/agent/index.blade.php`

- [ ] **Step 1: Escrever testes falhando para a navegacao**

Validar o HTML renderizado:

```php
$this->actingAs($agent)
    ->get(route('dashboard'))
    ->assertOk()
    ->assertSee('Agente')
    ->assertDontSee('Usuarios')
    ->assertDontSee('Supervisao')
    ->assertDontSee('Configuracoes')
    ->assertSee('Notificacoes');

$this->actingAs($supervisor)
    ->get(route('tenant.campaigns.index'))
    ->assertSee('Rodar discagem')
    ->assertDontSee('Nova campanha')
    ->assertDontSee('Editar');
```

Adicionar testes para:

- leitor de relatorios sem links de exportacao ou agendamento;
- usuario com `lists.create` vendo "Nova lista", mas sem editar ou inativar;
- administrador vendo todos os itens e comandos;
- Super Admin vendo somente Dashboard e Empresas.

- [ ] **Step 2: Executar e confirmar a falha**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantRbacNavigationTest.php
```

Expected: FAIL porque a sidebar e os comandos ainda sao incondicionais.

- [ ] **Step 3: Associar uma habilidade a cada item da sidebar**

Adicionar `ability` aos itens do tenant:

```php
['label' => 'Usuarios', 'ability' => 'tenant.users.read', ...],
['label' => 'Campanhas', 'ability' => 'tenant.campaigns.read', ...],
['label' => 'Agente', 'ability' => 'tenant.agent.access', ...],
['label' => 'Supervisao', 'ability' => 'tenant.supervision.read', ...],
```

Antes do `@foreach`, filtrar:

```php
$navItems = collect($navItems)
    ->filter(fn (array $item): bool => ! isset($item['ability']) || Gate::allows($item['ability']))
    ->values()
    ->all();
```

Importar ou referenciar `Illuminate\Support\Facades\Gate` de forma compativel com Blade.

Carregar e renderizar o painel lateral de notificacoes somente quando `tenant.notifications.read` for permitido:

```blade
@can('tenant.notifications.read')
    <x-ui.notification-panel :notifications="$notifications" :unread-count="$unreadCount" />
@endcan
```

Evitar tambem executar as consultas de notificacoes quando o usuario nao possuir essa permissao.

- [ ] **Step 4: Proteger comandos com `@can`**

Usar blocos explicitos:

```blade
@can('tenant.lists.create')
    <x-ui.button type="button" ...>Nova lista</x-ui.button>
@endcan

@can('tenant.lists.update')
    {{-- botao e modal de edicao --}}
@endcan

@can('tenant.lists.delete')
    {{-- botao e modal de inativacao --}}
@endcan
```

Aplicar:

- `users`, `teams`, `roles`, `lists`: create/update/delete;
- `campaigns`: create, update, delete e operate;
- `reports`: export e schedules;
- `settings`: create, update e delete;
- link "Abrir configuracoes" do painel do agente: `settings.read`.

Nao envolver conteudo de leitura em permissoes de escrita, pois a propria rota ja exige `.read`.

- [ ] **Step 5: Executar os testes de interface**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantRbacNavigationTest.php
```

Expected: PASS.

- [ ] **Step 6: Executar formatacao e testes relacionados**

Run:

```powershell
docker compose exec -T app vendor/bin/pint --test
docker compose exec -T app php artisan test tests/Feature/TenantRbacNavigationTest.php tests/Feature/TenantRbacAuthorizationTest.php
```

Expected: PASS.

- [ ] **Step 7: Commitar a interface autorizada**

```powershell
git add resources/views tests/Feature/TenantRbacNavigationTest.php
git commit -m "feat: filter tenant ui by permission"
```

### Task 5: Validacao completa no Docker e Windows

**Files:**
- Modify only if verification reveals a regression directly caused by this feature.

- [ ] **Step 1: Executar toda a suite PHP**

Run:

```powershell
docker compose exec -T app php artisan test
```

Expected: todos os testes aprovados.

- [ ] **Step 2: Executar testes JavaScript e build**

Run:

```powershell
npm test
npm run build
```

Expected: testes frontend e build aprovados.

- [ ] **Step 3: Verificar a tabela de rotas**

Run:

```powershell
docker compose exec -T app php artisan route:list --path=tenant -v
```

Expected: cada rota funcional do tenant exibe um middleware `can:tenant.*`.

- [ ] **Step 4: Fazer smoke test por perfil**

No navegador local em `http://localhost:8087`, validar:

- Administrador Demo: todos os modulos do tenant;
- Supervisor Demo: supervisao, campanhas e relatorios, sem painel do agente;
- Agente Demo: dashboard, agente e notificacoes, sem CRUDs;
- Super Admin: painel global sem links operacionais.

Confirmar tambem que uma URL proibida retorna `403`.

- [ ] **Step 5: Atualizar o roadmap**

Em `docs/ROADMAP_CONCLUSAO.md`, registrar RBAC aplicado em rotas e interface, mantendo separados os proximos bloqueios de licenciamento e concorrencia.

- [ ] **Step 6: Commitar verificacao e roadmap**

```powershell
git add docs/ROADMAP_CONCLUSAO.md
git commit -m "docs: complete tenant rbac milestone"
```
