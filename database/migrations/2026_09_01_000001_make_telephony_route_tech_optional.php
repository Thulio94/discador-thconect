<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->string('tech', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('telephony_routes')->whereNull('tech')->update(['tech' => '']);

        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->string('tech', 20)->nullable(false)->change();
        });
    }
};
