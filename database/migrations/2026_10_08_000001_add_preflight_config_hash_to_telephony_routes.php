<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephony_routes') || Schema::hasColumn('telephony_routes', 'preflight_config_hash')) {
            return;
        }

        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->char('preflight_config_hash', 64)->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('telephony_routes') && Schema::hasColumn('telephony_routes', 'preflight_config_hash')) {
            Schema::table('telephony_routes', function (Blueprint $table): void {
                $table->dropColumn('preflight_config_hash');
            });
        }
    }
};
