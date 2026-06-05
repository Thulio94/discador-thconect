<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function tenantUser(): User
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'extension' => '1001',
        ]);
    }
}
