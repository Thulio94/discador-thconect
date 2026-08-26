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

        if (app()->environment('production') && (blank($email) || strlen($password) < 12)) {
            throw new RuntimeException('INITIAL_ADMIN_EMAIL e INITIAL_ADMIN_PASSWORD forte sao obrigatorios em producao.');
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('INITIAL_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'user_type' => 'super_admin',
                'extension' => '1000',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
    }
}
