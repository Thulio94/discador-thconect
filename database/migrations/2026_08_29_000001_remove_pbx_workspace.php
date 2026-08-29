<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_KEYS = [
        'pbx.read',
        'pbx.manage',
        'pbx.call',
        'pbx.recordings',
        'pbx.monitor',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            // PABX is no longer part of the dialer. Keep memberships and call history,
            // but make legacy teams eligible for the default dialer operation again.
            DB::table('teams')
                ->where('operational_type', 'pbx')
                ->update([
                    'operational_type' => 'power',
                    'allow_agent_campaign_selection' => false,
                    'updated_at' => now(),
                ]);

            $permissionIds = DB::table('permissions')
                ->whereIn('key', self::PERMISSION_KEYS)
                ->pluck('id');

            DB::table('role_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        });
    }

    public function down(): void
    {
        foreach (self::PERMISSION_KEYS as $key) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => 'PABX',
                    'module' => 'pbx',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
};
