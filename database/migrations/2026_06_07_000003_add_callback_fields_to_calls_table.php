<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('callback_type')->nullable()->after('disposition_notes');
            $table->timestamp('callback_at')->nullable()->after('callback_type')->index();
            $table->json('service_fields')->nullable()->after('callback_at');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['callback_at']);
            $table->dropColumn(['callback_type', 'callback_at', 'service_fields']);
        });
    }
};
