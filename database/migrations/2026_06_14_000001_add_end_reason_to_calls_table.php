<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->string('end_reason')->nullable()->after('status');

            $table->index(['company_id', 'end_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'end_reason']);
            $table->dropColumn('end_reason');
        });
    }
};
