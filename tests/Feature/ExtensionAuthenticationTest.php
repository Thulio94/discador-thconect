<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExtensionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'email.login@test.local',
            'extension' => '2001',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login'), [
            'email' => ' EMAIL.LOGIN@TEST.LOCAL ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_extension_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'ramal.login@test.local',
            'extension' => '2002',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login'), [
            'email' => $user->extension,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_screen_only_offers_email_as_the_identifier(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertDontSee('name="login"', false)
            ->assertDontSee('E-mail ou ramal');
    }
}
