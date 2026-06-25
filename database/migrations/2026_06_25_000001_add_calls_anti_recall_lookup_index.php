<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->index(
                ['company_id', 'campaign_id', 'contact_id', 'user_id', 'answered_at'],
                'calls_anti_recall_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex('calls_anti_recall_lookup_index');
        });
    }
};
