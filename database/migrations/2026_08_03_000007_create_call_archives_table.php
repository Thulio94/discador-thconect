<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_archives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('disk', 40);
            $table->string('path');
            $table->unsignedBigInteger('records_count');
            $table->string('checksum', 64);
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->unique(['company_id', 'period_start']);
        });
    }

    public function down(): void { Schema::dropIfExists('call_archives'); }
};
