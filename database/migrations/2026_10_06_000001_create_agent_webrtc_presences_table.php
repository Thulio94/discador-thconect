<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_webrtc_presences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('connection_id');
            $table->boolean('registered')->default(false);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['user_id', 'connection_id']);
            $table->index(['company_id', 'registered', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_webrtc_presences');
    }
};
