<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->string('telephony_action_id')->nullable()->after('channel');
            $table->string('telephony_uniqueid')->nullable()->after('telephony_action_id');
            $table->string('telephony_channel')->nullable()->after('telephony_uniqueid');
            $table->string('amd_result')->nullable()->after('telephony_channel');
            $table->json('provider_payload')->nullable()->after('amd_result');

            $table->index('telephony_action_id');
            $table->index('telephony_uniqueid');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex(['telephony_action_id']);
            $table->dropIndex(['telephony_uniqueid']);
            $table->dropColumn([
                'telephony_action_id',
                'telephony_uniqueid',
                'telephony_channel',
                'amd_result',
                'provider_payload',
            ]);
        });
    }
};
