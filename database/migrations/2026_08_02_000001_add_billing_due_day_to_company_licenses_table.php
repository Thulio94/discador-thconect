<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_licenses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('billing_due_day')->nullable()->after('ends_at');
        });

        DB::table('company_licenses')->orderBy('id')->eachById(function (object $license): void {
            $dueDay = min(28, max(1, Carbon::parse($license->ends_at)->day));

            DB::table('company_licenses')
                ->where('id', $license->id)
                ->update(['billing_due_day' => $dueDay]);
        });
    }

    public function down(): void
    {
        Schema::table('company_licenses', function (Blueprint $table): void {
            $table->dropColumn('billing_due_day');
        });
    }
};
