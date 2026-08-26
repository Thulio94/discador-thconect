<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->char('event_key', 64);
            $table->string('telephony_uniqueid')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['call_id', 'event_key']);
            $table->index(['company_id', 'occurred_at']);
            $table->index(['call_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
