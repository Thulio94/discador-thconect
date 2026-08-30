<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_drive_backup_settings', function (Blueprint $table): void {
            $table->id();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('connected_email')->nullable();
            $table->string('root_folder_name')->default('THCONECT - Backup de Gravacoes');
            $table->string('root_folder_id')->nullable();
            $table->time('daily_at')->default('23:30');
            $table->boolean('enabled')->default(false);
            $table->boolean('delete_local_after_backup')->default(true);
            $table->date('last_dispatched_on')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('recording_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('call_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('drive_file_id')->nullable();
            $table->string('drive_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('attempts_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_backups');
        Schema::dropIfExists('google_drive_backup_settings');
    }
};
