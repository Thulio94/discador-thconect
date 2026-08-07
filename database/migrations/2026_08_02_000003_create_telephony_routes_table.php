<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('tech', 20);
            $table->string('host');
            $table->string('from_domain')->nullable();
            $table->string('transport', 10)->default('udp');
            $table->string('authentication_mode', 20)->default('ip');
            $table->string('sip_username')->nullable();
            $table->text('sip_password')->nullable();
            $table->string('caller_id')->nullable();
            $table->string('dial_prefix', 40)->nullable();
            $table->string('dialplan_context')->default('thconect-outbound');
            $table->string('dialplan_extension')->default('s');
            $table->string('codecs')->default('alaw,g729');
            $table->boolean('enabled')->default(false);
            $table->string('connection_status')->default('not_configured');
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('telephony_route_id')
                ->nullable()
                ->after('status')
                ->constrained('telephony_routes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('telephony_route_id');
        });

        Schema::dropIfExists('telephony_routes');
    }
};
