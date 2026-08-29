<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('operational_type')->default('power')->after('status');
            $table->boolean('allow_agent_campaign_selection')->default(false)->after('operational_type');
            $table->index(['company_id', 'operational_type', 'status'], 'teams_company_operational_status_index');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('aggressiveness')->nullable()->after('agent_distribution_strategy');
            $table->timestamp('last_dispatched_at')->nullable()->after('ends_at');
            $table->index(['company_id', 'status', 'dialing_mode', 'priority'], 'campaigns_dispatch_index');
        });

        DB::table('campaigns')->where('dialing_mode', 'predictive')->update(['dialing_mode' => 'power']);
        DB::table('campaigns')->where('dialing_mode', 'manual')->update(['dialing_mode' => 'preview']);

        Schema::create('campaign_phone_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_phone_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('last_result')->nullable();
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('recycle_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_phone_id']);
            $table->index(['company_id', 'campaign_id', 'status', 'next_attempt_at'], 'campaign_phone_state_queue_index');
            $table->index(['company_id', 'contact_list_id', 'status'], 'campaign_phone_state_list_status_index');
        });

        Schema::create('mailing_recycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('filters');
            $table->unsignedInteger('affected_count')->default(0);
            $table->string('request_key', 64)->unique();
            $table->timestamps();

            $table->index(['company_id', 'campaign_id', 'contact_list_id'], 'mailing_recycles_scope_index');
        });

        Schema::create('agent_campaign_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'ended_at'], 'agent_campaign_sessions_current_index');
            $table->index(['company_id', 'campaign_id', 'ended_at'], 'agent_campaign_sessions_campaign_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_campaign_sessions');
        Schema::dropIfExists('mailing_recycles');
        Schema::dropIfExists('campaign_phone_states');

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('campaigns_dispatch_index');
            $table->dropColumn(['aggressiveness', 'last_dispatched_at']);
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropIndex('teams_company_operational_status_index');
            $table->dropColumn(['operational_type', 'allow_agent_campaign_selection']);
        });
    }
};
