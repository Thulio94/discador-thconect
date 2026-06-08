<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 12);
            $table->json('filters')->nullable();
            $table->unsignedInteger('records_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
