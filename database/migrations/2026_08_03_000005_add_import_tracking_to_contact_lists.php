<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_lists', function (Blueprint $table): void {
            $table->string('import_status', 20)->default('idle')->after('status');
            $table->unsignedTinyInteger('import_progress')->default(0)->after('import_status');
            $table->unsignedInteger('imported_rows')->default(0)->after('import_progress');
            $table->text('import_error')->nullable()->after('imported_rows');
            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();
            $table->index(['company_id', 'import_status']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'import_status']);
            $table->dropColumn(['import_status', 'import_progress', 'imported_rows', 'import_error', 'import_started_at', 'import_completed_at']);
        });
    }
};
