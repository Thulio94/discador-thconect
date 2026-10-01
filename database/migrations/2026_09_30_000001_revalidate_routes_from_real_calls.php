<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MIGRATION_MARKER = 'Revalidacao de chamada real necessaria.';

    public function up(): void
    {
        if (! Schema::hasTable('telephony_routes') || ! Schema::hasTable('calls') || ! Schema::hasTable('call_events')) {
            return;
        }

        if (! Schema::hasColumn('telephony_routes', 'connection_status')
            || ! Schema::hasColumn('telephony_routes', 'last_error')
            || ! Schema::hasColumn('calls', 'telephony_route_id')
            || ! Schema::hasColumn('call_events', 'call_id')) {
            return;
        }

        DB::table('telephony_routes')
            ->where('connection_status', 'online')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('calls')
                    ->join('call_events', 'call_events.call_id', '=', 'calls.id')
                    ->whereColumn('calls.telephony_route_id', 'telephony_routes.id')
                    ->where('call_events.event', 'originate_succeeded');
            })
            ->update([
                'connection_status' => 'pending_test',
                'last_error' => self::MIGRATION_MARKER,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('telephony_routes')
            || ! Schema::hasColumn('telephony_routes', 'connection_status')
            || ! Schema::hasColumn('telephony_routes', 'last_error')) {
            return;
        }

        DB::table('telephony_routes')
            ->where('connection_status', 'pending_test')
            ->where('last_error', self::MIGRATION_MARKER)
            ->update([
                'connection_status' => 'online',
                'last_error' => null,
                'updated_at' => now(),
            ]);
    }
};
