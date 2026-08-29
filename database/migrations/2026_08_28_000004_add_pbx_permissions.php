<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'pbx.read' => 'Ler',
        'pbx.manage' => 'Gerenciar grupos e ramais',
        'pbx.call' => 'Realizar chamadas',
        'pbx.recordings' => 'Acessar gravações',
        'pbx.monitor' => 'Monitorar chamadas',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $key => $name) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'module' => 'pbx', 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
