<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCampaignListCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_contact_list_with_contacts(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Junho',
                'description' => 'Leads de junho',
                'status' => 'active',
                'contacts' => "Maria;31999990001\nJoao;31999990002",
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status');

        $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista Junho')->sole();

        $this->assertSame('active', $list->status);
        $this->assertSame(2, $list->contacts()->count());
        $this->assertTrue($list->contacts()->where('phone', '31999990001')->exists());
    }

    public function test_tenant_admin_can_view_contact_lists_index(): void
    {
        [$admin] = $this->tenantAdmin();

        ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Existente',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.lists.index'))
            ->assertOk()
            ->assertSee('Lista Existente')
            ->assertSee('Nova lista');
    }

    public function test_tenant_admin_can_update_and_inactivate_contact_list(): void
    {
        [$admin] = $this->tenantAdmin();
        $list = ContactList::create([
            'company_id' => $admin->company_id,
            'name' => 'Lista Antiga',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('tenant.lists.update', $list), [
                'name' => 'Lista Atualizada',
                'description' => 'Base revisada',
                'status' => 'active',
                'contacts' => 'Pedro;31999990003',
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status');

        $list->refresh();

        $this->assertSame('Lista Atualizada', $list->name);
        $this->assertSame(1, $list->contacts()->count());

        $this->actingAs($admin)
            ->delete(route('tenant.lists.destroy', $list))
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $list->fresh()->status);
    }

    public function test_tenant_admin_can_create_campaign_with_multiple_teams_and_lists(): void
    {
        [$admin, $teamA] = $this->tenantAdmin();
        $teamB = Team::create(['company_id' => $admin->company_id, 'name' => 'Retencao', 'slug' => 'retencao']);
        $listA = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista A']);
        $listB = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista B']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Vendas',
                'description' => 'Operacao ativa',
                'dialing_mode' => 'preview',
                'status' => 'draft',
                'team_ids' => [$teamA->id, $teamB->id],
                'list_ids' => [$listA->id, $listB->id],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Vendas')->sole();

        $this->assertSame('preview', $campaign->dialing_mode);
        $this->assertSame(2, $campaign->teams()->count());
        $this->assertSame(2, $campaign->contactLists()->count());
    }

    public function test_tenant_admin_can_update_and_finish_campaign(): void
    {
        [$admin, $teamA] = $this->tenantAdmin();
        $teamB = Team::create(['company_id' => $admin->company_id, 'name' => 'Suporte', 'slug' => 'suporte']);
        $listA = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista A']);
        $listB = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista B']);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Antiga',
            'dialing_mode' => 'preview',
            'status' => 'draft',
        ]);
        $campaign->teams()->sync([$teamA->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$listA->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->put(route('tenant.campaigns.update', $campaign), [
                'name' => 'Campanha Atualizada',
                'description' => 'Operacao revisada',
                'dialing_mode' => 'power',
                'status' => 'active',
                'priority' => 4,
                'team_ids' => [$teamA->id, $teamB->id],
                'list_ids' => [$listA->id, $listB->id],
            ])
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign->refresh();

        $this->assertSame('Campanha Atualizada', $campaign->name);
        $this->assertSame('power', $campaign->dialing_mode);
        $this->assertSame(2, $campaign->teams()->count());
        $this->assertSame(2, $campaign->contactLists()->count());

        $this->actingAs($admin)
            ->delete(route('tenant.campaigns.destroy', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('finished', $campaign->fresh()->status);
    }

    public function test_campaign_cannot_use_team_or_list_from_another_company(): void
    {
        [$admin] = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherTeam = Team::create(['company_id' => $otherCompany->id, 'name' => 'Outra Equipe', 'slug' => 'outra-equipe']);
        $otherList = ContactList::create(['company_id' => $otherCompany->id, 'name' => 'Outra Lista']);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.store'), [
                'name' => 'Campanha Invalida',
                'dialing_mode' => 'preview',
                'status' => 'draft',
                'team_ids' => [$otherTeam->id],
                'list_ids' => [$otherList->id],
            ])
            ->assertSessionHasErrors(['team_ids.0', 'list_ids.0']);
    }

    private function tenantAdmin(): array
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Vendas',
            'slug' => 'vendas',
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => '1000',
            'status' => 'active',
        ]);

        return [$admin, $team];
    }
}
