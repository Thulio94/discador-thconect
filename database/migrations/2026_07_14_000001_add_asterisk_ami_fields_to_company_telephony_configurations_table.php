<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_telephony_configurations', function (Blueprint $table): void {
            $table->string('driver')->default('simulated')->after('company_id');
            $table->string('ami_host')->nullable()->after('caller_id');
            $table->unsignedSmallInteger('ami_port')->default(5038)->after('ami_host');
            $table->text('ami_username')->nullable()->after('ami_port');
            $table->text('ami_secret')->nullable()->after('ami_username');
            $table->string('outbound_channel_template')->nullable()->after('ami_secret');
            $table->string('dialplan_context')->nullable()->after('outbound_channel_template');
            $table->string('dialplan_extension')->default('s')->after('dialplan_context');
            $table->boolean('bridge_enabled')->default(false)->after('dialplan_extension');
            $table->string('connection_status')->default('not_configured')->after('bridge_enabled');
            $table->timestamp('last_checked_at')->nullable()->after('connection_status');
            $table->text('last_error')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('company_telephony_configurations', function (Blueprint $table): void {
            $table->dropColumn([
                'driver',
                'ami_host',
                'ami_port',
                'ami_username',
                'ami_secret',
                'outbound_channel_template',
                'dialplan_context',
                'dialplan_extension',
                'bridge_enabled',
                'connection_status',
                'last_checked_at',
                'last_error',
            ]);
        });
    }
};
