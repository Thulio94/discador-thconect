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
        // The previous unique index is intentionally not restored because
        // duplicate primary phones are now allowed across different leads.
    }
};
