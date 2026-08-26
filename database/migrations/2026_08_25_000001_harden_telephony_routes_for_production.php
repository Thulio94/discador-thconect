<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sip_port')->default(5060)->after('host');
            $table->string('outbound_proxy')->nullable()->after('from_domain');
            $table->string('from_user')->nullable()->after('sip_password');
            $table->string('contact_user')->nullable()->after('from_user');
            $table->string('dtmf_mode', 20)->default('rfc4733')->after('codecs');
            $table->string('caller_id_header', 12)->default('none')->after('dtmf_mode');
            $table->unsignedSmallInteger('qualify_frequency')->default(15)->after('caller_id_header');
            $table->unsignedInteger('registration_expiration')->default(3600)->after('qualify_frequency');
            $table->unsignedSmallInteger('priority')->default(100)->after('registration_expiration');
        });
    }

    public function down(): void
    {
        Schema::table('telephony_routes', function (Blueprint $table): void {
            $table->dropColumn([
                'sip_port',
                'outbound_proxy',
                'from_user',
                'contact_user',
                'dtmf_mode',
                'caller_id_header',
                'qualify_frequency',
                'registration_expiration',
                'priority',
            ]);
        });
    }
};
