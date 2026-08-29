<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_lead_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_phone_state_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'released_at'], 'preview_reservation_user_index');
            $table->index(['company_id', 'campaign_id', 'released_at'], 'preview_reservation_campaign_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_lead_reservations');
    }
};
