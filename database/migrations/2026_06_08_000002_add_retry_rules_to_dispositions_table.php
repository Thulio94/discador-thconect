<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositions', function (Blueprint $table): void {
            $table->boolean('retry_enabled')->default(false)->after('is_success');
            $table->unsignedInteger('retry_delay_minutes')->nullable()->after('retry_enabled');
            $table->unsignedInteger('max_attempts')->nullable()->after('retry_delay_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('dispositions', function (Blueprint $table): void {
            $table->dropColumn(['retry_enabled', 'retry_delay_minutes', 'max_attempts']);
        });
    }
};
