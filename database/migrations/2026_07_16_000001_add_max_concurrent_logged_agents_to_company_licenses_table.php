<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_licenses', function (Blueprint $table): void {
            $table->unsignedInteger('max_concurrent_logged_agents')->nullable()->after('max_concurrent_agents');
        });
    }

    public function down(): void
    {
        Schema::table('company_licenses', function (Blueprint $table): void {
            $table->dropColumn('max_concurrent_logged_agents');
        });
    }
};
