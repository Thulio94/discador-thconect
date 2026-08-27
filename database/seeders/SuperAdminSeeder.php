<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('INITIAL_ADMIN_EMAIL', 'admin@discador.local');
        $password = (string) env('INITIAL_ADMIN_PASSWORD', app()->environment('production') ? '' : 'password');
        $synchronizePassword = filter_var(env('INITIAL_ADMIN_SYNC_PASSWORD', false), FILTER_VALIDATE_BOOL);

        if (app()->environment('production') && (blank($email) || strlen($password) < 12)) {
            throw new RuntimeException('INITIAL_ADMIN_EMAIL e INITIAL_ADMIN_PASSWORD forte sao obrigatorios em producao.');
        }

        $admin = User::query()->firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $admin->fill([
                'name' => (string) env('INITIAL_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'user_type' => 'super_admin',
                'extension' => '1000',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $admin->save();

            return;
        }

        if ($synchronizePassword) {
            $admin->forceFill([
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'user_type' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => $admin->email_verified_at ?? now(),
            ])->save();
        }
    }
}
