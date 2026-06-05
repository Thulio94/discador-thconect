<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Tenant\PermissionMatrix;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionMatrix::modules() as $moduleKey => $module) {
            foreach ($module['permissions'] as $permission) {
                Permission::query()->updateOrCreate(
                    ['key' => $permission['key']],
                    [
                        'name' => $permission['label'],
                        'module' => $moduleKey,
                    ],
                );
            }
        }
    }
}
