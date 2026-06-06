<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->foreignId('disposition_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
            $table->string('disposition_code')->nullable()->after('disposition_id');
            $table->string('disposition_name')->nullable()->after('disposition_code');
            $table->text('disposition_notes')->nullable()->after('disposition_name');

            $table->index(['company_id', 'disposition_id']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'disposition_id']);
            $table->dropConstrainedForeignId('disposition_id');
            $table->dropColumn(['disposition_code', 'disposition_name', 'disposition_notes']);
        });
    }
};
