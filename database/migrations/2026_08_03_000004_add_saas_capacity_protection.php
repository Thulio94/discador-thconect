<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_capacity_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('max_channels')->default(500);
            $table->unsignedTinyInteger('warning_percent')->default(70);
            $table->unsignedTinyInteger('critical_percent')->default(85);
            $table->unsignedTinyInteger('cpu_warning_percent')->default(70);
            $table->unsignedTinyInteger('cpu_critical_percent')->default(85);
            $table->unsignedTinyInteger('memory_warning_percent')->default(75);
            $table->unsignedTinyInteger('memory_critical_percent')->default(90);
            $table->unsignedTinyInteger('database_warning_percent')->default(70);
            $table->unsignedTinyInteger('database_critical_percent')->default(85);
            $table->unsignedInteger('telephony_queue_warning_ms')->default(1000);
            $table->unsignedInteger('telephony_queue_block_ms')->default(3000);
            $table->boolean('origination_blocked')->default(false);
            $table->timestamps();
        });

        DB::table('system_capacity_settings')->insert([
            'max_channels' => 500,
            'warning_percent' => 70,
            'critical_percent' => 85,
            'cpu_warning_percent' => 70,
            'cpu_critical_percent' => 85,
            'memory_warning_percent' => 75,
            'memory_critical_percent' => 90,
            'database_warning_percent' => 70,
            'database_critical_percent' => 85,
            'telephony_queue_warning_ms' => 1000,
            'telephony_queue_block_ms' => 3000,
            'origination_blocked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('company_resource_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_registered_users')->default(1000);
            $table->unsignedInteger('max_active_contacts')->default(500000);
            $table->unsignedInteger('max_import_rows')->default(100000);
            $table->unsignedTinyInteger('max_concurrent_imports')->default(1);
            $table->unsignedSmallInteger('max_active_campaigns')->default(20);
            $table->unsignedSmallInteger('max_active_webhooks')->default(20);
            $table->unsignedTinyInteger('max_concurrent_exports')->default(2);
            $table->unsignedInteger('max_csv_rows')->default(1000000);
            $table->unsignedInteger('max_xlsx_rows')->default(200000);
            $table->unsignedInteger('max_pdf_rows')->default(10000);
            $table->unsignedSmallInteger('max_report_range_days')->default(90);
            $table->unsignedInteger('max_daily_attempts')->default(500000);
            $table->unsignedTinyInteger('dialer_weight')->default(1);
            $table->unsignedTinyInteger('hot_history_months')->default(24);
            $table->timestamps();
        });

        DB::table('companies')->orderBy('id')->eachById(function (object $company): void {
            DB::table('company_resource_limits')->insert([
                'company_id' => $company->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::create('asterisk_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('bridge_url');
            $table->text('bridge_token')->nullable();
            $table->unsignedSmallInteger('max_channels')->default(170);
            $table->unsignedSmallInteger('current_channels')->default(0);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('status', 24)->default('maintenance');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        $localNodeId = DB::table('asterisk_nodes')->insertGetId([
            'name' => 'Asterisk local',
            'bridge_url' => 'http://asterisk-bridge:8090',
            'max_channels' => 170,
            'priority' => 100,
            'status' => 'online',
            'last_heartbeat_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->foreignId('asterisk_node_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('max_concurrent_channels')->nullable()->after('codecs');
        });

        DB::table('telephony_routes')->update(['asterisk_node_id' => $localNodeId]);

        Schema::create('channel_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('telephony_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asterisk_node_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('reserved');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['company_id', 'status']);
            $table->index(['telephony_route_id', 'status']);
            $table->index(['asterisk_node_id', 'status']);
        });

        Schema::table('calls', function (Blueprint $table): void {
            $table->uuid('channel_reservation_id')->nullable()->after('telephony_route_id');
            $table->foreign('channel_reservation_id')->references('id')->on('channel_reservations')->nullOnDelete();
            $table->index(['company_id', 'status', 'started_at'], 'calls_company_status_started_idx');
            $table->index(['telephony_route_id', 'status', 'started_at'], 'calls_route_status_started_idx');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex('calls_company_status_started_idx');
            $table->dropIndex('calls_route_status_started_idx');
            $table->dropForeign(['channel_reservation_id']);
            $table->dropColumn('channel_reservation_id');
        });
        Schema::dropIfExists('channel_reservations');
        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asterisk_node_id');
            $table->dropColumn('max_concurrent_channels');
        });
        Schema::dropIfExists('asterisk_nodes');
        Schema::dropIfExists('company_resource_limits');
        Schema::dropIfExists('system_capacity_settings');
    }
};
