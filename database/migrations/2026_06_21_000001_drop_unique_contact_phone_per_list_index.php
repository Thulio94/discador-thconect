<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique(['contact_list_id', 'phone']);
        });
    }

    public function down(): void
    {
        // The previous unique index is intentionally not restored: after this
        // migration, duplicate primary phones can be valid across different
        // leads, so rolling back the index could fail or reject preserved data.
    }
};
