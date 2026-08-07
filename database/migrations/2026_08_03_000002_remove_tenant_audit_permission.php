<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $permissionId = DB::table('permissions')
                ->where('key', 'audit.read')
                ->value('id');

            if ($permissionId === null) {
                return;
            }

            DB::table('role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();

            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        });
    }

    public function down(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'audit.read'],
            [
                'name' => 'Ler',
                'module' => 'audit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
