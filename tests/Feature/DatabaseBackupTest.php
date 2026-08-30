<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use App\Models\DatabaseBackupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_manage_or_download_database_backups(): void
    {
        Storage::fake('local');
        $tenant = User::factory()->create();
        $admin = User::factory()->create(['is_super_admin' => true, 'company_id' => null]);
        $backup = DatabaseBackup::create(['status' => 'completed', 'file_path' => 'database-backups/test.sql.gz']);
        Storage::disk('local')->put($backup->file_path, 'backup');
        $this->actingAs($tenant)->get(route('super-admin.database-backup.index'))->assertForbidden();
        $response = $this->actingAs($admin)->get(route('super-admin.database-backup.index'));
        $response->assertOk()->assertSee('Backup de dados');
        $this->actingAs($admin)->get(route('super-admin.database-backup.download', $backup))->assertOk();
    }

    public function test_super_admin_can_schedule_database_backups(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'company_id' => null]);
        $this->actingAs($admin)->put(route('super-admin.database-backup.update'), ['enabled' => '1', 'daily_at' => '03:15', 'keep_local_copy' => '1'])->assertRedirect();
        $this->assertDatabaseHas('database_backup_settings', ['enabled' => true, 'daily_at' => '03:15', 'keep_local_copy' => true]);
        $this->assertTrue(DatabaseBackupSetting::current()->enabled);
    }
}
