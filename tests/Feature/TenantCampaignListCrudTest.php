<?php

namespace Tests\Feature;

use App\Models\BlacklistNumber;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\MailingQueueService;
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
        $this->assertSame('pending', $list->contacts()->where('phone', '31999990001')->sole()->status);
        $this->assertSame(0, $list->contacts()->where('phone', '31999990001')->sole()->attempts_count);
    }

    public function test_contact_list_import_sanitizes_deduplicates_and_applies_tenant_blacklist(): void
    {
        [$admin] = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);

        BlacklistNumber::create([
            'company_id' => $admin->company_id,
            'phone' => '31999990002',
            'reason' => 'DNC',
            'status' => 'active',
        ]);
        BlacklistNumber::create([
            'company_id' => $otherCompany->id,
            'phone' => '31999990003',
            'reason' => 'Outro tenant',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Higienizada',
                'description' => 'Com blacklist',
                'status' => 'active',
                'contacts' => implode("\n", [
                    'Maria;(31) 99999-0001',
                    'Maria duplicada;31999990001',
                    'Bloqueado;31999990002',
                    'Outro tenant;31999990003',
                    'Invalido;12345',
                ]),
            ])
            ->assertRedirect(route('tenant.lists.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '2 contatos importados')
                && str_contains($message, '1 bloqueado')
                && str_contains($message, '1 invalido')
                && str_contains($message, '1 duplicado'));

        $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista Higienizada')->sole();

        $this->assertSame(2, $list->contacts()->count());
        $this->assertTrue($list->contacts()->where('phone', '31999990001')->exists());
        $this->assertTrue($list->contacts()->where('phone', '31999990003')->exists());
        $this->assertFalse($list->contacts()->where('phone', '31999990002')->exists());
        $this->assertFalse($list->contacts()->where('phone', '12345')->exists());
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
            ->assertSee('Nova lista')
            ->assertSee('Blacklist aplicada automaticamente');
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

    public function test_tenant_admin_can_run_operational_campaign_actions_and_duplicate_campaign(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Operacional']);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Operacional',
            'dialing_mode' => 'preview',
            'status' => 'draft',
            'priority' => 3,
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.start', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->starts_at);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.pause', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('paused', $campaign->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.restart', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $this->assertSame('active', $campaign->fresh()->status);

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.duplicate', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $copy = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Operacional - copia')->sole();

        $this->assertSame('draft', $copy->status);
        $this->assertSame('preview', $copy->dialing_mode);
        $this->assertSame(3, $copy->priority);
        $this->assertSame([$team->id], $copy->teams()->pluck('teams.id')->all());
        $this->assertSame([$list->id], $copy->contactLists()->pluck('contact_lists.id')->all());

        $this->actingAs($admin)
            ->delete(route('tenant.campaigns.destroy', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status');

        $campaign->refresh();

        $this->assertSame('finished', $campaign->status);
        $this->assertNotNull($campaign->ends_at);
    }

    public function test_campaign_operational_actions_are_tenant_scoped(): void
    {
        [$admin] = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherCampaign = Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha de Outro Tenant',
            'dialing_mode' => 'preview',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.start', $otherCampaign))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('tenant.campaigns.duplicate', $otherCampaign))
            ->assertForbidden();

        $this->assertSame('draft', $otherCampaign->fresh()->status);
    }

    public function test_mailing_queue_selects_next_contact_and_marks_attempt_with_cooldown(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Fila', 'status' => 'active']);
        $future = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Cooldown',
            'phone' => '31999990001',
            'status' => 'pending',
            'next_attempt_at' => now()->addHour(),
        ]);
        $ready = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Pronto',
            'phone' => '31999990002',
            'status' => 'pending',
        ]);
        $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Atendido',
            'phone' => '31999990003',
            'status' => 'answered',
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Fila',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id, 'weight' => 2]]);

        $service = app(MailingQueueService::class);

        $this->assertSame($ready->id, $service->nextContactForCampaign($campaign)->id);
        $this->assertSame([
            'pending' => 2,
            'dialing' => 0,
            'answered' => 1,
            'unproductive' => 0,
            'callback' => 0,
            'exhausted' => 0,
        ], $service->summaryForCampaign($campaign));

        $service->markAttempt($ready, now()->addMinutes(30));
        $ready->refresh();

        $this->assertSame('dialing', $ready->status);
        $this->assertSame(1, $ready->attempts_count);
        $this->assertNotNull($ready->last_attempt_at);
        $this->assertNotNull($ready->next_attempt_at);
        $this->assertSame($future->id, $future->fresh()->id);
        $this->assertNull($service->nextContactForCampaign($campaign));
    }

    public function test_tenant_admin_can_recycle_campaign_mailing_and_keep_tenant_scope(): void
    {
        [$admin, $team] = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Reciclagem', 'status' => 'active']);
        $recyclable = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Reciclar',
            'phone' => '31999990004',
            'status' => 'unproductive',
            'attempts_count' => 2,
            'last_result' => 'ocupado',
            'last_attempt_at' => now()->subHour(),
            'next_attempt_at' => now()->addHour(),
        ]);
        $answered = $list->contacts()->create([
            'company_id' => $admin->company_id,
            'name' => 'Atendido',
            'phone' => '31999990005',
            'status' => 'answered',
            'attempts_count' => 1,
        ]);
        $campaign = Campaign::create([
            'company_id' => $admin->company_id,
            'name' => 'Campanha Reciclagem',
            'dialing_mode' => 'preview',
            'status' => 'paused',
        ]);
        $campaign->teams()->sync([$team->id => ['company_id' => $admin->company_id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $admin->company_id]]);
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherCampaign = Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha Outra',
            'dialing_mode' => 'preview',
            'status' => 'paused',
        ]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.recycle', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 contato reciclado'));

        $recyclable->refresh();
        $answered->refresh();

        $this->assertSame('pending', $recyclable->status);
        $this->assertNull($recyclable->next_attempt_at);
        $this->assertSame(2, $recyclable->attempts_count);
        $this->assertSame('answered', $answered->status);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.recycle', $otherCampaign))
            ->assertForbidden();
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
