<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backup_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->time('daily_at')->default('02:30');
            $table->boolean('send_to_google_drive')->default(false);
            $table->boolean('keep_local_copy')->default(true);
            $table->date('last_dispatched_on')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('database_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('queued');
            $table->string('file_path')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
        Schema::dropIfExists('database_backup_settings');
    }
};
