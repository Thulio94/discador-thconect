<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantUserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_is_generated_automatically_for_tenant_users(): void
    {
        [$admin, $role] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.users.index'));

        $user = User::where('email', 'agente@demo.test')->sole();

        $this->assertGreaterThanOrEqual(1000, (int) $user->extension);
        $this->assertLessThanOrEqual(10000, (int) $user->extension);
    }

    public function test_tenant_admin_can_create_agent_with_team_membership(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();

        $response = $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'phone' => '31999990000',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'))
            ->assertSessionHas('status');

        $agent = User::where('email', 'agente@demo.test')->sole();

        $this->assertSame($admin->company_id, $agent->company_id);
        $this->assertGreaterThanOrEqual(1000, (int) $agent->extension);
        $this->assertLessThanOrEqual(10000, (int) $agent->extension);
        $credentials = $response->getSession()->get('temporary_credentials');
        $this->assertCount(1, $credentials);
        $this->assertSame($agent->email, $credentials[0]['email']);
        $this->assertSame(20, strlen($credentials[0]['password']));
        $this->assertTrue(Hash::check($credentials[0]['password'], $agent->password));
        $this->assertFalse(Hash::check('password', $agent->password));
        $this->assertSame('agent', $agent->teams()->first()->pivot->membership_type);
    }

    public function test_agent_has_one_team_and_can_be_assigned_to_multiple_campaigns(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();
        $secondTeam = Team::create(['company_id' => $admin->company_id, 'name' => 'Equipe Dois', 'slug' => 'equipe-dois']);
        $campaignA = Campaign::create(['company_id' => $admin->company_id, 'name' => 'Campanha A']);
        $campaignB = Campaign::create(['company_id' => $admin->company_id, 'name' => 'Campanha B']);

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente Multi Equipe',
                'email' => 'agente-multi-equipe@demo.test',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id, $secondTeam->id],
            ])
            ->assertSessionHasErrors('team_ids');

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente Campanhas',
                'email' => 'agente-campanhas@demo.test',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
                'campaign_ids' => [$campaignA->id, $campaignB->id],
            ])
            ->assertRedirect(route('tenant.users.index'));

        $agent = User::where('email', 'agente-campanhas@demo.test')->sole();

        $this->assertSame([$team->id], $agent->assignedTeams()->pluck('teams.id')->all());
        $this->assertEqualsCanonicalizing([$campaignA->id, $campaignB->id], $agent->assignedCampaigns()->pluck('campaigns.id')->all());
    }

    public function test_supervisor_can_belong_to_multiple_teams(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();
        $secondTeam = Team::create(['company_id' => $admin->company_id, 'name' => 'Equipe Dois', 'slug' => 'equipe-dois']);

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Supervisor Multi Equipe',
                'email' => 'supervisor-equipes@demo.test',
                'user_type' => 'supervisor',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id, $secondTeam->id],
            ])
            ->assertRedirect(route('tenant.users.index'));

        $supervisor = User::where('email', 'supervisor-equipes@demo.test')->sole();

        $this->assertEqualsCanonicalizing([$team->id, $secondTeam->id], $supervisor->assignedTeams()->pluck('teams.id')->all());
    }

    public function test_assigning_an_agent_in_another_team_moves_the_agent_to_that_team(): void
    {
        [$admin, $role, $firstTeam] = $this->tenantAdmin();
        $secondTeam = Team::create(['company_id' => $admin->company_id, 'name' => 'Equipe Dois', 'slug' => 'equipe-dois']);
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '2001',
        ]);
        $agent->assignedTeams()->attach($firstTeam->id, ['company_id' => $admin->company_id, 'membership_type' => 'agent']);

        $this->actingAs($admin)
            ->put(route('tenant.teams.update', $secondTeam), [
                'name' => $secondTeam->name,
                'status' => 'active',
                'agent_ids' => [$agent->id],
            ])
            ->assertRedirect(route('tenant.teams.index'));

        $this->assertSame([$secondTeam->id], $agent->fresh()->assignedTeams()->pluck('teams.id')->all());
    }

    public function test_tenant_admin_can_create_enumerated_users_in_bulk_and_export_them(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();

        $response = $this->actingAs($admin)
            ->post(route('tenant.users.bulk.store'), [
                'name_prefix' => 'Agente Vendas',
                'email_base' => 'vendas.lote@demo.test',
                'quantity' => 3,
                'confirmed' => true,
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'))
            ->assertSessionHas('status', '3 usuarios criados com sucesso.');

        $credentials = $response->getSession()->get('temporary_credentials');
        $this->assertCount(3, $credentials);
        $this->assertCount(3, array_unique(array_column($credentials, 'password')));
        foreach ($credentials as $credential) {
            $this->assertSame(20, strlen($credential['password']));
            $this->assertTrue(Hash::check($credential['password'], User::where('email', $credential['email'])->sole()->password));
        }

        $this->assertDatabaseHas('users', ['name' => 'Agente Vendas 01', 'email' => 'vendas.lote+001@demo.test']);
        $this->assertDatabaseHas('users', ['name' => 'Agente Vendas 03', 'email' => 'vendas.lote+003@demo.test']);

        $this->actingAs($admin)
            ->get(route('tenant.users.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_bulk_creation_requires_the_review_confirmation(): void
    {
        [$admin, $role] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->from(route('tenant.users.index'))
            ->post(route('tenant.users.bulk.store'), [
                'name_prefix' => 'Agente Vendas',
                'email_base' => 'vendas@demo.test',
                'quantity' => 1,
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
                'confirmed' => false,
            ])
            ->assertRedirect(route('tenant.users.index'))
            ->assertSessionHasErrors('confirmed');

        $this->assertDatabaseMissing('users', ['email' => 'vendas+001@demo.test']);
    }

    public function test_user_index_exposes_assignment_selector_bulk_creation_and_export(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->get(route('tenant.users.index'))
            ->assertOk()
            ->assertSee('Campanhas do agente')
            ->assertSee('Criacao em massa')
            ->assertSee('Exportar usuarios');
    }

    public function test_active_users_are_not_limited_by_the_legacy_license_user_limit(): void
    {
        [$admin, $role] = $this->tenantAdmin(maxUsers: 1);

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.users.index'))
            ->assertSessionHas('status');
    }

    public function test_email_is_normalized_and_must_be_unique_across_companies(): void
    {
        [$firstAdmin] = $this->tenantAdmin();
        [$secondAdmin, $secondRole] = $this->tenantAdmin();

        User::factory()->create([
            'company_id' => $firstAdmin->company_id,
            'role_id' => $firstAdmin->role_id,
            'email' => 'usuario.global@demo.test',
        ]);

        $this->actingAs($secondAdmin)
            ->post(route('tenant.users.store'), [
                'name' => 'Mesmo e-mail em outra empresa',
                'email' => ' USUARIO.GLOBAL@DEMO.TEST ',
                'user_type' => 'agent',
                'role_id' => $secondRole->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors([
                'email' => 'Este e-mail ja esta vinculado a outro usuario do sistema.',
            ]);

        $this->assertDatabaseCount('users', 3);
    }

    public function test_extension_is_allocated_globally_and_ignores_manual_input(): void
    {
        [$admin, $role] = $this->tenantAdmin();

        $existingUser = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'extension' => '2001',
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'user_type' => 'agent',
                'extension' => $existingUser->extension,
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.users.index'));

        $newUser = User::where('email', 'agente@demo.test')->sole();

        $this->assertNotSame($existingUser->extension, $newUser->extension);
    }

    public function test_tenant_admin_can_regenerate_a_users_extension(): void
    {
        [$admin, $role] = $this->tenantAdmin();
        $user = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'extension' => '2001',
        ]);

        $this->actingAs($admin)
            ->patch(route('tenant.users.extension.regenerate', $user))
            ->assertRedirect(route('tenant.users.index'));

        $this->assertNotSame('2001', $user->fresh()->extension);
        $this->assertGreaterThanOrEqual(1000, (int) $user->extension);
        $this->assertLessThanOrEqual(10000, (int) $user->extension);
    }

    public function test_user_index_filters_by_search_status_type_role_and_team_with_tenant_scope(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();
        [$otherAdmin, , $otherTeam] = $this->tenantAdmin();
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'name' => 'Agente Busca Especial',
            'email' => 'busca-especial@demo.test',
            'user_type' => 'agent',
            'extension' => '2456',
            'status' => 'active',
        ]);
        $team->users()->attach($agent, ['company_id' => $admin->company_id, 'membership_type' => 'agent']);
        User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'name' => 'Supervisor Fora Filtro',
            'user_type' => 'supervisor',
            'extension' => '3456',
            'status' => 'inactive',
        ]);
        User::factory()->create([
            'company_id' => $otherAdmin->company_id,
            'role_id' => $otherAdmin->role_id,
            'name' => 'Agente Outra Empresa',
            'user_type' => 'agent',
            'extension' => '4456',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.users.index', [
                'q' => 'Busca Especial',
                'status' => 'active',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertSee('Agente Busca Especial')
            ->assertSee('user-view-'.$agent->id)
            ->assertSee('user-edit-'.$agent->id)
            ->assertSee('user-delete-'.$agent->id)
            ->assertDontSee('Supervisor Fora Filtro')
            ->assertDontSee('Agente Outra Empresa');

        $this->actingAs($admin)
            ->get(route('tenant.users.index', ['team_id' => $otherTeam->id]))
            ->assertSessionHasErrors('team_id');
    }

    public function test_user_creation_and_deactivation_are_audited_without_noop_events(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();
        AuditLog::query()->delete();

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente Auditado',
                'email' => 'auditado@demo.test',
                'user_type' => 'agent',
                'extension' => '2099',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'));

        $agent = User::where('email', 'auditado@demo.test')->sole();
        $created = AuditLog::where('event', 'user.created')->where('subject_id', $agent->id)->sole();

        $this->assertSame($admin->id, $created->actor_user_id);
        $this->assertArrayNotHasKey('password', $created->new_values);

        $originalExtension = $agent->extension;

        $this->actingAs($admin)
            ->put(route('tenant.users.update', $agent), [
                'name' => $agent->name,
                'email' => $agent->email,
                'user_type' => $agent->user_type,
                'extension' => $agent->extension,
                'role_id' => $role->id,
                'status' => 'inactive',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'));

        $deactivated = AuditLog::where('event', 'user.deactivated')->where('subject_id', $agent->id)->sole();

        $this->assertSame('active', $deactivated->old_values['status']);
        $this->assertSame($originalExtension, $deactivated->old_values['extension']);
        $this->assertSame('inactive', $deactivated->new_values['status']);
        $this->assertNull($deactivated->new_values['extension']);

        $count = AuditLog::count();
        $agent->fresh()->save();

        $this->assertSame($count, AuditLog::count());
    }

    public function test_inactive_users_release_their_extension_and_reactivation_reserves_one(): void
    {
        [$admin, $role] = $this->tenantAdmin();
        $user = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '7001',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('tenant.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('tenant.users.index'));

        $this->assertNull($user->fresh()->extension);

        $this->actingAs($admin)
            ->put(route('tenant.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.users.index'));

        $reactivated = $user->fresh();

        $this->assertNotNull($reactivated->extension);
        $this->assertGreaterThanOrEqual(1000, (int) $reactivated->extension);
        $this->assertLessThanOrEqual(10000, (int) $reactivated->extension);
        $this->assertSame(1, User::query()->where('extension', $reactivated->extension)->count());
    }

    public function test_tenant_admin_can_change_a_users_password(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '2001',
            'status' => 'active',
            'password' => Hash::make('senha-anterior'),
        ]);
        $agent->teams()->attach($team->id, ['company_id' => $admin->company_id, 'membership_type' => 'agent']);

        $this->actingAs($admin)
            ->put(route('tenant.users.update', $agent), [
                'name' => $agent->name,
                'email' => $agent->email,
                'user_type' => $agent->user_type,
                'extension' => $agent->extension,
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
                'password' => 'nova-senha-segura',
                'password_confirmation' => 'nova-senha-segura',
            ])
            ->assertRedirect(route('tenant.users.index'))
            ->assertSessionHas('status', 'Usuario atualizado e senha alterada com sucesso.');

        $this->assertTrue(Hash::check('nova-senha-segura', $agent->fresh()->password));
        $this->assertFalse(Hash::check('senha-anterior', $agent->fresh()->password));
    }

    public function test_password_change_requires_matching_confirmation(): void
    {
        [$admin, $role] = $this->tenantAdmin();
        $agent = User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '2001',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('tenant.users.update', $agent), [
                'name' => $agent->name,
                'email' => $agent->email,
                'user_type' => $agent->user_type,
                'extension' => $agent->extension,
                'role_id' => $role->id,
                'status' => 'active',
                'password' => 'nova-senha-segura',
                'password_confirmation' => 'confirmacao-diferente',
            ])
            ->assertSessionHasErrors('password');
    }

    private function tenantAdmin(int $maxUsers = 10): array
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => $maxUsers,
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe',
            'slug' => 'equipe',
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => (string) (1000 + User::query()->count()),
            'status' => 'active',
        ]);

        return [$admin, $role, $team];
    }
}
