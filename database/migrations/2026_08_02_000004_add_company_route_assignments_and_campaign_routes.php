<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_telephony_route', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telephony_route_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'telephony_route_id']);
        });

        DB::table('companies')
            ->whereNotNull('telephony_route_id')
            ->orderBy('id')
            ->each(function (object $company): void {
                DB::table('company_telephony_route')->insertOrIgnore([
                    'company_id' => $company->id,
                    'telephony_route_id' => $company->telephony_route_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreignId('fixed_telephony_route_id')
                ->nullable()
                ->after('dialing_route')
                ->constrained('telephony_routes')
                ->nullOnDelete();
            $table->foreignId('mobile_telephony_route_id')
                ->nullable()
                ->after('fixed_telephony_route_id')
                ->constrained('telephony_routes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fixed_telephony_route_id');
            $table->dropConstrainedForeignId('mobile_telephony_route_id');
        });

        Schema::dropIfExists('company_telephony_route');
    }
};
