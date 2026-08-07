<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_global_and_all_company_events_with_detail_modals(): void
    {
        $companyA = Company::create(['name' => 'Tenant A']);
        $companyB = Company::create(['name' => 'Tenant B']);
        $admin = $this->superAdmin();
        AuditLog::query()->delete();
        $logA = $this->log($companyA->id, 'Evento exclusivo A', 'user.updated');
        $this->log($companyB->id, 'Evento exclusivo B', 'company.updated');
        $this->log(null, 'Evento global de seguranca', 'auth.failed');

        $this->actingAs($admin)
            ->get(route('super-admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Evento exclusivo A')
            ->assertSee('Evento exclusivo B')
            ->assertSee('Evento global de seguranca')
            ->assertSee('audit-detail-'.$logA->id, false)
            ->assertSee('data-audit-table', false)
            ->assertSee('table-fixed', false)
            ->assertDontSee('alert(', false)
            ->assertDontSee('window.confirm', false);
    }

    public function test_tenant_audit_url_is_not_available_and_navigation_is_hidden(): void
    {
        [$admin] = $this->tenantAdmin('Tenant A');

        $this->actingAs($admin)
            ->get('/tenant/audit-logs')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Auditoria');
    }

    public function test_audit_permission_is_not_available_to_tenant_roles(): void
    {
        $this->seed(PermissionSeeder::class);
        $company = $this->company('Tenant Restrito');
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Supervisor',
            'slug' => 'supervisor',
            'is_admin' => false,
        ]);
        $role->permissions()->attach(Permission::where('key', 'dashboard.read')->sole());
        $supervisor = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'supervisor',
            'extension' => '1200',
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->get('/tenant/audit-logs')
            ->assertNotFound();

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Auditoria');
    }

    public function test_removing_legacy_tenant_audit_permission_preserves_audit_logs(): void
    {
        $company = $this->company('Tenant Legado');
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador legado',
            'slug' => 'administrador-legado',
            'is_admin' => true,
        ]);
        $permission = Permission::create([
            'key' => 'audit.read',
            'name' => 'Ler',
            'module' => 'audit',
        ]);
        $role->permissions()->attach($permission);
        $log = $this->log($company->id, 'Evento que deve permanecer', 'user.updated');

        $migration = require database_path('migrations/2026_08_03_000002_remove_tenant_audit_permission.php');
        $migration->up();

        $this->assertDatabaseMissing('permissions', ['key' => 'audit.read']);
        $this->assertDatabaseMissing('role_permissions', ['permission_id' => $permission->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_audit_filters_apply_date_actor_event_global_and_text_search(): void
    {
        $company = Company::create(['name' => 'Tenant Filtro']);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $admin = $this->superAdmin();
        AuditLog::query()->delete();
        $matching = $this->log(
            $company->id,
            'Alteracao especial pesquisavel',
            'user.updated',
            $actor->id,
            '2026-06-15 10:00:00',
        );
        $this->log($company->id, 'Fora do periodo', 'user.updated', $actor->id, '2026-05-01 10:00:00');
        $this->log(null, 'Global no periodo', 'auth.failed', null, '2026-06-15 11:00:00');

        $this->actingAs($admin)
            ->get(route('super-admin.audit-logs.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'company_id' => $company->id,
                'actor_user_id' => $actor->id,
                'event' => 'user.updated',
                'search' => 'especial pesquisavel',
            ]))
            ->assertOk()
            ->assertSee($matching->description)
            ->assertDontSee('Fora do periodo')
            ->assertDontSee('Global no periodo');

        $this->actingAs($admin)
            ->get(route('super-admin.audit-logs.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'global_only' => '1',
            ]))
            ->assertOk()
            ->assertSee('Global no periodo')
            ->assertDontSee($matching->description);
    }

    private function log(
        ?int $companyId,
        string $description,
        string $event,
        ?int $actorId = null,
        string $createdAt = '2026-06-20 10:00:00',
    ): AuditLog {
        return AuditLog::create([
            'company_id' => $companyId,
            'actor_user_id' => $actorId,
            'event' => $event,
            'action' => str($event)->afterLast('.')->toString(),
            'description' => $description,
            'created_at' => $createdAt,
        ]);
    }

    private function tenantAdmin(string $name): array
    {
        $company = $this->company($name);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => fake()->unique()->numerify('1###'),
            'status' => 'active',
        ]);

        return [$admin, $company];
    }

    private function company(string $name): Company
    {
        $company = Company::create(['name' => $name, 'status' => 'active']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 10,
        ]);

        return $company;
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
