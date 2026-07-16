<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('endpoint', 2048);
            $table->text('secret');
            $table->json('events');
            $table->string('status')->default('active');
            $table->timestamp('last_tested_at')->nullable();
            $table->unsignedSmallInteger('last_response_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_webhooks');
    }
};
