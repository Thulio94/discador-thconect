<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_telephony_configurations', function (Blueprint $table): void {
            $table->string('supervisor_channel_template')->default('PJSIP/{extension}')->after('outbound_channel_template');
        });

        $permissionId = DB::table('permissions')
            ->where('key', 'supervision.operate')
            ->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'key' => 'supervision.operate',
                'name' => 'Operar supervisao',
                'module' => 'supervision',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.key', 'supervision.read')
            ->pluck('role_permissions.role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'supervision.operate')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('company_telephony_configurations', function (Blueprint $table): void {
            $table->dropColumn('supervisor_channel_template');
        });
    }
};
