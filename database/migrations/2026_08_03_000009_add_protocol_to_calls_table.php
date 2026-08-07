<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->char('protocol', 12)->nullable()->after('phone');
        });

        DB::table('calls')->orderBy('id')->each(function (object $call): void {
            $prefix = Carbon::parse($call->started_at ?? now())->format('ymd');
            $protocol = $prefix.str_pad((string) ($call->id % 1000000), 6, '0', STR_PAD_LEFT);

            DB::table('calls')->where('id', $call->id)->update(['protocol' => $protocol]);
        });

        Schema::table('calls', function (Blueprint $table): void {
            $table->unique('protocol');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropUnique(['protocol']);
            $table->dropColumn('protocol');
        });
    }
};
