<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_phone_states', function (Blueprint $table): void {
            $table->foreignId('last_disposition_id')->nullable()->after('last_result')->constrained('dispositions')->nullOnDelete();
            $table->index(['company_id', 'campaign_id', 'contact_list_id', 'last_disposition_id'], 'campaign_phone_state_disposition_index');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_phone_states', function (Blueprint $table): void {
            $table->dropIndex('campaign_phone_state_disposition_index');
            $table->dropConstrainedForeignId('last_disposition_id');
        });
    }
};
