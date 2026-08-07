<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('recording_retention_days')->nullable()->after('status');
        });

        Schema::table('calls', function (Blueprint $table): void {
            $table->foreignId('telephony_route_id')->nullable()->after('campaign_id')->constrained()->nullOnDelete();
            $table->string('hangup_party', 16)->nullable()->after('end_reason');
            $table->string('recording_path')->nullable()->after('provider_payload');
            $table->string('recording_format', 12)->nullable()->after('recording_path');
            $table->unsignedBigInteger('recording_size')->nullable()->after('recording_format');
            $table->timestamp('recording_available_at')->nullable()->after('recording_size');
            $table->json('report_snapshot')->nullable()->after('service_fields');
            $table->index(['company_id', 'started_at'], 'calls_company_started_index');
            $table->index(['company_id', 'campaign_id', 'started_at'], 'calls_company_campaign_started_index');
            $table->index(['company_id', 'user_id', 'started_at'], 'calls_company_user_started_index');
        });

        Schema::create('call_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_call_id')->constrained('calls')->cascadeOnDelete();
            $table->foreignId('completion_call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_phone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone');
            $table->string('type', 12);
            $table->string('status', 16)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->unique('source_call_id');
            $table->index(['company_id', 'status', 'scheduled_at']);
            $table->index(['company_id', 'assigned_user_id', 'scheduled_at']);
        });

        Schema::table('report_exports', function (Blueprint $table): void {
            $table->string('report_type', 32)->default('calls')->after('user_id');
            $table->string('status', 16)->default('completed')->after('format');
            $table->unsignedTinyInteger('progress')->default(100)->after('status');
            $table->string('file_path')->nullable()->after('filters');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_path');
            $table->text('error_message')->nullable()->after('file_size');
            $table->timestamp('started_at')->nullable()->after('error_message');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('expires_at')->nullable()->after('completed_at');
            $table->index(['company_id', 'status', 'created_at'], 'report_exports_company_status_created_index');
        });

        Schema::table('report_schedules', function (Blueprint $table): void {
            $table->string('report_type', 32)->default('calls')->after('user_id');
        });

        DB::table('report_exports')->whereNull('completed_at')->update([
            'completed_at' => DB::raw('created_at'),
            'started_at' => DB::raw('created_at'),
            'status' => 'completed',
            'progress' => 100,
        ]);
    }

    public function down(): void
    {
        Schema::table('report_schedules', function (Blueprint $table): void {
            $table->dropColumn('report_type');
        });
        Schema::table('report_exports', function (Blueprint $table): void {
            $table->dropIndex('report_exports_company_status_created_index');
            $table->dropColumn(['report_type', 'status', 'progress', 'file_path', 'file_size', 'error_message', 'started_at', 'completed_at', 'expires_at']);
        });
        Schema::dropIfExists('call_callbacks');
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex('calls_company_started_index');
            $table->dropIndex('calls_company_campaign_started_index');
            $table->dropIndex('calls_company_user_started_index');
            $table->dropConstrainedForeignId('telephony_route_id');
            $table->dropColumn(['hangup_party', 'recording_path', 'recording_format', 'recording_size', 'recording_available_at', 'report_snapshot']);
        });
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('recording_retention_days');
        });
    }
};
