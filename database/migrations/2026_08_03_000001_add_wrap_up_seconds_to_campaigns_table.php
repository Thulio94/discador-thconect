<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('wrap_up_seconds')->default(60)->after('wrap_up_minutes');
        });

        DB::table('campaigns')->update([
            'wrap_up_seconds' => DB::raw('wrap_up_minutes * 60'),
        ]);
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('wrap_up_seconds');
        });
    }
};
