<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedInteger('max_attempts_per_contact')->nullable()->after('lead_phone_strategy');
            $table->unsignedInteger('retry_delay_minutes')->nullable()->after('max_attempts_per_contact');
            $table->unsignedInteger('max_concurrent_calls')->nullable()->after('retry_delay_minutes');
            $table->string('amd_mode')->default('disabled')->after('max_concurrent_calls');
            $table->string('dialing_route')->nullable()->after('amd_mode');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'max_attempts_per_contact',
                'retry_delay_minutes',
                'max_concurrent_calls',
                'amd_mode',
                'dialing_route',
            ]);
        });
    }
};
