<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->foreignId('contact_phone_id')->nullable()->after('contact_id')->constrained('contact_phones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_phone_id');
        });
    }
};
