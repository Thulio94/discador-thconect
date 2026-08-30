<?php

namespace Tests\Feature;

use App\Jobs\BackupCallRecordingToGoogleDrive;
use App\Models\Call;
use App\Models\Company;
use App\Models\GoogleDriveBackupSetting;
use App\Models\User;
use App\Services\Recordings\GoogleDriveRecordingBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleDriveRecordingBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_open_the_private_google_drive_backup_configuration(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'company_id' => null]);
        $tenantUser = User::factory()->create();

        $this->actingAs($tenantUser)
            ->get(route('super-admin.recording-backup.index'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('super-admin.recording-backup.index'))
            ->assertOk()
            ->assertSee('Backup de gravações')
            ->assertDontSee('Segredo OAuth configurado');
    }

    public function test_super_admin_can_save_encrypted_google_drive_configuration_and_dispatch_backup(): void
    {
        Bus::fake();
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'company_id' => null]);

        $this->actingAs($superAdmin)
            ->put(route('super-admin.recording-backup.update'), [
                'client_id' => 'client-id.apps.googleusercontent.com',
                'client_secret' => 'client-secret',
                'root_folder_name' => 'Backup Discador',
                'daily_at' => '23:30',
                'delete_local_after_backup' => '1',
            ])
            ->assertRedirect(route('super-admin.recording-backup.index'));

        $setting = GoogleDriveBackupSetting::current();
        $this->assertSame('client-id.apps.googleusercontent.com', $setting->client_id);
        $this->assertSame('client-secret', $setting->client_secret);
        $this->assertTrue($setting->delete_local_after_backup);
        $setting->update([
            'refresh_token' => 'refresh-token',
            'enabled' => true,
        ]);

        $company = Company::create(['name' => 'Empresa da fila', 'status' => 'active']);
        Call::create([
            'company_id' => $company->id,
            'phone' => '31999990002',
            'status' => 'completed',
            'channel' => 'simulated',
            'direction' => 'outbound',
            'started_at' => now()->subDay(),
            'recording_disk' => 'recordings',
            'recording_path' => 'recordings/fila.wav',
            'recording_available_at' => now()->subDay(),
        ]);

        $this->actingAs($superAdmin)
            ->post(route('super-admin.recording-backup.run'))
            ->assertRedirect(route('super-admin.recording-backup.index'));

        Bus::assertBatched(fn ($batch) => $batch->jobs->contains(fn ($job) => $job instanceof BackupCallRecordingToGoogleDrive));
    }

    public function test_confirmed_google_drive_upload_moves_recording_and_preserves_a_company_index(): void
    {
        Storage::fake('recordings');
        $company = Company::create(['name' => 'Empresa Áudio', 'status' => 'active']);
        $call = Call::create([
            'company_id' => $company->id,
            'phone' => '31999990001',
            'status' => 'completed',
            'channel' => 'simulated',
            'direction' => 'outbound',
            'started_at' => now()->subDay(),
            'recording_disk' => 'recordings',
            'recording_path' => 'recordings/teste.wav',
            'recording_format' => 'wav',
            'recording_available_at' => now()->subDay(),
        ]);
        Storage::disk('recordings')->put($call->recording_path, 'audio-test');

        GoogleDriveBackupSetting::current()->update([
            'client_id' => 'client-id.apps.googleusercontent.com',
            'client_secret' => 'client-secret',
            'refresh_token' => 'refresh-token',
            'enabled' => true,
            'delete_local_after_backup' => true,
        ]);

        $service = \Mockery::mock(GoogleDriveRecordingBackup::class);
        $service->shouldReceive('backup')->once()->with(\Mockery::on(fn (Call $recording) => $recording->id === $call->id))
            ->andReturn('drive-file-123');
        $this->app->instance(GoogleDriveRecordingBackup::class, $service);

        (new BackupCallRecordingToGoogleDrive($call->id))->handle($service);

        $this->assertSame('google_drive', $call->fresh()->recording_disk);
        $this->assertSame('drive-file-123', $call->fresh()->recording_path);
        Storage::disk('recordings')->assertMissing('recordings/teste.wav');
        $this->assertDatabaseHas('recording_backups', ['call_id' => $call->id, 'status' => 'completed', 'drive_file_id' => 'drive-file-123']);
    }
}
