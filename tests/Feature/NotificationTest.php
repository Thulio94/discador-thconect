<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_persisted_for_user(): void
    {
        $user = $this->tenantUser();

        $notification = app(AppNotificationService::class)->createForUser(
            $user,
            'system',
            'success',
            'Bem-vindo',
            'Sua operacao esta pronta.',
            '/dashboard'
        );

        $this->assertSame($user->company_id, $notification->company_id);
        $this->assertSame($user->id, $notification->user_id);
        $this->assertDatabaseHas('app_notifications', [
            'title' => 'Bem-vindo',
            'severity' => 'success',
        ]);
    }

    public function test_notification_can_be_marked_as_read(): void
    {
        $user = $this->tenantUser();
        $notification = app(AppNotificationService::class)->createForUser(
            $user,
            'system',
            'info',
            'Aviso',
            'Mensagem'
        );

        $this->actingAs($user)
            ->patch(route('tenant.notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_all_user_notifications_can_be_marked_as_read(): void
    {
        $user = $this->tenantUser();
        app(AppNotificationService::class)->createForUser($user, 'system', 'info', 'A', 'Mensagem');
        app(AppNotificationService::class)->createForUser($user, 'system', 'info', 'B', 'Mensagem');

        $this->actingAs($user)
            ->patch(route('tenant.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->appNotifications()->whereNull('read_at')->count());
    }

    public function test_notification_poll_returns_only_current_user_panel_and_unread_state(): void
    {
        $company = $this->company();
        $user = $this->tenantUser($company, 'company_admin', '1001');
        $otherUser = $this->tenantUser($company, 'supervisor', '1002');

        $read = app(AppNotificationService::class)->createForUser(
            $user,
            'system',
            'info',
            'Aviso lido',
            'Mensagem lida'
        );
        $read->update(['read_at' => now()]);
        app(AppNotificationService::class)->createForUser(
            $user,
            'system',
            'warning',
            'Aviso novo',
            'Mensagem nova'
        );
        app(AppNotificationService::class)->createForUser(
            $otherUser,
            'system',
            'info',
            'Aviso externo',
            'Nao mostrar'
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

    public function test_campaign_operational_action_notifies_company_admins_and_supervisors(): void
    {
        $company = $this->company();
        $admin = $this->tenantUser($company, 'company_admin', '1001');
        $supervisor = $this->tenantUser($company, 'supervisor', '1002');
        $agent = $this->tenantUser($company, 'agent', '1003');
        $otherAdmin = $this->tenantUser($this->company('Outra Empresa'), 'company_admin', '2001');
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Notificada',
            'dialing_mode' => 'power',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->patch(route('tenant.campaigns.start', $campaign))
            ->assertRedirect(route('tenant.campaigns.index'));

        $this->assertDatabaseHas('app_notifications', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'type' => 'campaign',
            'severity' => 'success',
            'title' => 'Campanha iniciada',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'company_id' => $company->id,
            'user_id' => $supervisor->id,
            'title' => 'Campanha iniciada',
        ]);
        $this->assertFalse($agent->appNotifications()->where('title', 'Campanha iniciada')->exists());
        $this->assertFalse($otherAdmin->appNotifications()->where('title', 'Campanha iniciada')->exists());
    }

    public function test_contact_list_import_notifies_company_admins_and_supervisors(): void
    {
        $company = $this->company();
        $admin = $this->tenantUser($company, 'company_admin', '1001');
        $supervisor = $this->tenantUser($company, 'supervisor', '1002');

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista Notificada',
                'description' => 'Importacao operacional',
                'status' => 'active',
                'contacts' => "Maria;31999990001\nJoao;31999990002",
            ])
            ->assertRedirect(route('tenant.lists.index'));

        $this->assertDatabaseHas('app_notifications', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'type' => 'mailing',
            'severity' => 'info',
            'title' => 'Lista importada',
            'message' => 'Lista Notificada recebeu 2 contatos importados.',
        ]);
        $this->assertTrue($supervisor->appNotifications()->where('title', 'Lista importada')->exists());
    }

    public function test_csv_contact_list_import_notifies_company_admins_and_supervisors(): void
    {
        $company = $this->company();
        $admin = $this->tenantUser($company, 'company_admin', '1001');
        $supervisor = $this->tenantUser($company, 'supervisor', '1002');
        $file = UploadedFile::fake()->createWithContent(
            'leads.csv',
            "Nome;Telefone 1;Telefone 2\nMaria;31999990001;31988880002\nJoao;31977770003;"
        );

        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista CSV Notificada',
                'status' => 'active',
                'import_file' => $file,
                'separator' => ';',
                'delimiter' => '"',
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                    ['index' => 1, 'enabled' => '1', 'name' => 'Telefone 1', 'type' => 'phone'],
                    ['index' => 2, 'enabled' => '1', 'name' => 'Telefone 2', 'type' => 'phone'],
                ],
            ])
            ->assertRedirect(route('tenant.lists.index'));

        $this->assertDatabaseHas('app_notifications', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'type' => 'mailing',
            'severity' => 'info',
            'title' => 'Lista importada',
            'message' => 'Lista CSV Notificada recebeu 2 leads e 3 telefones importados.',
        ]);
        $this->assertTrue($supervisor->appNotifications()->where('message', 'Lista CSV Notificada recebeu 2 leads e 3 telefones importados.')->exists());
    }

    public function test_supervision_operational_alerts_create_deduplicated_notifications(): void
    {
        $company = $this->company();
        $supervisor = $this->tenantUser($company, 'supervisor', '1002');
        $agent = $this->tenantUser($company, 'agent', '1003');

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'paused',
            'started_at' => now()->subMinutes(25),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990044',
            'started_at' => now()->subMinutes(18),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.poll'))
            ->assertOk();
        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.poll'))
            ->assertOk();

        $this->assertSame(1, $supervisor->appNotifications()->where('title', 'Pausa longa')->count());
        $this->assertSame(1, $supervisor->appNotifications()->where('title', 'Chamada sem tabulacao')->count());
    }

    private function company(string $name = 'Tenant A'): Company
    {
        $company = Company::create(['name' => $name]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        return $company;
    }

    private function tenantUser(?Company $company = null, string $type = 'company_admin', string $extension = '1001'): User
    {
        $company ??= $this->company();
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Operacao '.$extension,
            'slug' => 'operacao-'.$extension,
            'is_admin' => true,
        ]);
        if ($type === 'supervisor') {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['key' => 'supervision.read'],
                ['name' => 'Ler', 'module' => 'supervision'],
            ));
        }

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $type,
            'status' => 'active',
            'extension' => $extension,
        ]);
    }
}
