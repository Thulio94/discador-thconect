<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->uuid('dial_batch_id')->nullable()->after('campaign_id');
            $table->index(['company_id', 'dial_batch_id', 'status'], 'calls_power_batch_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex('calls_power_batch_status_index');
            $table->dropColumn('dial_batch_id');
        });
    }
};
