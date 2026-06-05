<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->string('membership_type');
            $table->timestamps();

            $table->unique(['company_id', 'team_id', 'user_id', 'membership_type']);
            $table->foreign(['company_id', 'team_id'])->references(['company_id', 'id'])->on('teams')->cascadeOnDelete();
            $table->foreign(['company_id', 'user_id'])->references(['company_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
