<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('wrap_up_minutes')->default(1)->after('retry_delay_minutes');
        });

        Schema::table('calls', function (Blueprint $table): void {
            $table->timestamp('wrap_up_expires_at')->nullable()->after('ended_at');
            $table->index(['status', 'wrap_up_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropIndex(['status', 'wrap_up_expires_at']);
            $table->dropColumn('wrap_up_expires_at');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('wrap_up_minutes');
        });
    }
};
