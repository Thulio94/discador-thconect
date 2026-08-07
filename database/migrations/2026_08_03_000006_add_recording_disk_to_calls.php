<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->string('recording_disk', 40)->nullable()->after('recording_path');
        });
    }

    public function down(): void
    {
        Schema::table('calls', fn (Blueprint $table) => $table->dropColumn('recording_disk'));
    }
};
