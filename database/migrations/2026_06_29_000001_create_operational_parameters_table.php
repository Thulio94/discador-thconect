<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('min_pause_minutes')->default(0);
            $table->unsignedSmallInteger('max_pause_minutes')->default(120);
            $table->unsignedSmallInteger('pause_alert_minutes')->default(20);
            $table->unsignedSmallInteger('call_alert_minutes')->default(15);
            $table->boolean('auto_available_after_call')->default(true);
            $table->boolean('allow_manual_calls_while_paused')->default(false);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_parameters');
    }
};
