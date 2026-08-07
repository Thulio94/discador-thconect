<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->selectRaw('LOWER(TRIM(email)) as normalized_email, COUNT(*) as total')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_email')
            ->filter()
            ->values();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'A migracao foi interrompida sem alterar dados. Existem e-mails duplicados: '.$duplicates->join(', ')
            );
        }

        DB::table('users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->each(function (object $user): void {
                $normalizedEmail = mb_strtolower(trim((string) $user->email));

                if ($normalizedEmail !== $user->email) {
                    DB::table('users')->where('id', $user->id)->update(['email' => $normalizedEmail]);
                }
            });
    }

    public function down(): void
    {
        // A normalizacao em minusculas nao deve ser revertida.
    }
};
