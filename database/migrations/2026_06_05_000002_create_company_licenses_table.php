<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('max_users')->default(10);
            $table->unsignedInteger('max_concurrent_agents')->nullable();
            $table->unsignedInteger('max_calls_per_available_agent')->default(1);
            $table->unsignedInteger('max_concurrent_calls')->nullable();
            $table->boolean('power_enabled')->default(true);
            $table->boolean('predictive_enabled')->default(false);
            $table->boolean('preview_enabled')->default(true);
            $table->boolean('amd_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_licenses');
    }
};
