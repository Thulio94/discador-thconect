<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('format', 12);
            $table->string('frequency', 12);
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->time('run_time');
            $table->json('filters')->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_records_count')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'frequency', 'run_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
