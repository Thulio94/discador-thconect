<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Tenant\PermissionMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->updateOrCreate(
            ['document' => '00000000000191'],
            [
                'name' => 'THCONECT Demo',
                'status' => 'active',
            ],
        );

        $company->licenses()->updateOrCreate(
            ['status' => 'active'],
            [
                'starts_at' => now()->subDay()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
                'max_users' => 10,
                'max_concurrent_agents' => 4,
                'max_calls_per_available_agent' => 2,
                'max_concurrent_calls' => 8,
                'power_enabled' => true,
                'predictive_enabled' => false,
                'preview_enabled' => true,
                'amd_enabled' => false,
            ],
        );

        $adminRole = Role::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'slug' => 'administrador',
            ],
            [
                'name' => 'Administrador',
                'is_admin' => true,
            ],
        );

        $adminRole->permissions()->sync(
            Permission::query()
                ->whereIn('key', PermissionMatrix::keys())
                ->pluck('id')
                ->all()
        );

        $companyAdmin = $this->user($company, $adminRole, 'Administrador Demo', 'admin.demo@discador.local', 'company_admin', '1100');
        $supervisor = $this->user($company, $adminRole, 'Supervisor Demo', 'supervisor.demo@discador.local', 'supervisor', '1200');
        $agentOne = $this->user($company, $adminRole, 'Agente Demo 1', 'agente1.demo@discador.local', 'agent', '1301');
        $agentTwo = $this->user($company, $adminRole, 'Agente Demo 2', 'agente2.demo@discador.local', 'agent', '1302');

        $team = Team::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'slug' => Str::slug('Equipe Demo'),
            ],
            [
                'name' => 'Equipe Demo',
                'status' => 'active',
            ],
        );

        $team->users()->syncWithoutDetaching([
            $supervisor->id => ['membership_type' => 'supervisor'],
            $agentOne->id => ['membership_type' => 'agent'],
            $agentTwo->id => ['membership_type' => 'agent'],
        ]);
    }

    private function user(Company $company, Role $role, string $name, string $email, string $type, string $extension): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make('password'),
                'user_type' => $type,
                'extension' => $extension,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
    }
}
