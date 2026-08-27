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

        $admin = User::query()->where('email', $email)->first();

        if ($admin && (! $admin->is_super_admin || $admin->company_id !== null)) {
            throw new RuntimeException('INITIAL_ADMIN_EMAIL ja pertence a um usuario empresarial.');
        }

        if (! $admin && $synchronizePassword) {
            $centralAdmins = User::query()
                ->where('is_super_admin', true)
                ->where('user_type', 'super_admin')
                ->whereNull('company_id')
                ->orderBy('id')
                ->limit(2)
                ->get();

            if ($centralAdmins->count() > 1) {
                throw new RuntimeException('Ha mais de um super admin central; informe um e-mail existente.');
            }

            $admin = $centralAdmins->first();
        }

        $admin ??= new User;

        if (! $admin->exists) {
            $admin->fill([
                'name' => (string) env('INITIAL_ADMIN_NAME', 'Super Admin'),
                'email' => $email,
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
                'email' => $email,
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'user_type' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => $admin->email_verified_at ?? now(),
            ])->save();
        }
    }
}
