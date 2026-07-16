<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_licenses')
            ->whereNotNull('max_concurrent_logged_agents')
            ->update([
                'max_concurrent_agents' => DB::raw('max_concurrent_logged_agents'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('company_licenses')
            ->whereNotNull('max_concurrent_agents')
            ->update([
                'max_concurrent_logged_agents' => DB::raw('max_concurrent_agents'),
                'updated_at' => now(),
            ]);
    }
};
