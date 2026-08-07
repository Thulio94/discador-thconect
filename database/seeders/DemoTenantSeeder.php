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
                'billing_due_day' => min(28, now()->day),
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

        $adminRole = $this->role(
            $company,
            'Administrador',
            'administrador',
            PermissionMatrix::keys(),
            true,
        );

        $supervisorRole = $this->role($company, 'Supervisor', 'supervisor', [
            'dashboard.read',
            'teams.read',
            'lists.read',
            'campaigns.read',
            'campaigns.operate',
            'agent.access',
            'supervision.read',
            'supervision.operate',
            'reports.read',
            'reports.export',
            'reports.schedules',
            'notifications.read',
            'notifications.update',
        ]);

        $agentRole = $this->role($company, 'Agente', 'agente', [
            'dashboard.read',
            'agent.access',
            'notifications.read',
            'notifications.update',
        ]);

        $companyAdmin = $this->user($company, $adminRole, 'Administrador Demo', 'admin.demo@discador.local', 'company_admin', '1100');
        $supervisor = $this->user($company, $supervisorRole, 'Supervisor Demo', 'supervisor.demo@discador.local', 'supervisor', '1200');
        $agentOne = $this->user($company, $agentRole, 'Agente Demo 1', 'agente1.demo@discador.local', 'agent', '1301');
        $agentTwo = $this->user($company, $agentRole, 'Agente Demo 2', 'agente2.demo@discador.local', 'agent', '1302');

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

    private function role(
        Company $company,
        string $name,
        string $slug,
        array $permissionKeys,
        bool $isAdmin = false,
    ): Role {
        $role = Role::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'slug' => $slug,
            ],
            [
                'name' => $name,
                'is_admin' => $isAdmin,
            ],
        );

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('key', $permissionKeys)
                ->pluck('id')
                ->all()
        );

        return $role;
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
