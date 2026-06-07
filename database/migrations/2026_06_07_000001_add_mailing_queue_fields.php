<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->unsignedInteger('attempts_count')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('attempts_count');
            $table->timestamp('next_attempt_at')->nullable()->after('last_attempt_at');
            $table->string('last_result')->nullable()->after('next_attempt_at');

            $table->index(['company_id', 'contact_list_id', 'status', 'next_attempt_at'], 'contacts_queue_lookup_index');
        });

        Schema::table('campaign_contact_list', function (Blueprint $table): void {
            $table->unsignedInteger('weight')->default(1)->after('contact_list_id');
        });

        DB::table('contacts')->where('status', 'new')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('campaign_contact_list', function (Blueprint $table): void {
            $table->dropColumn('weight');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex('contacts_queue_lookup_index');
            $table->dropColumn(['attempts_count', 'last_attempt_at', 'next_attempt_at', 'last_result']);
        });
    }
};
