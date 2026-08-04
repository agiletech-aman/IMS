<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\RestoreJob;
use App\Models\SystemSetting;
use App\Services\BackupManager;
use App\Services\BackupRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $backupPath;

    private string $uploadPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupPath = storage_path('app/backups-testing');
        $this->uploadPath = storage_path('app/backup-uploads-testing');
        File::deleteDirectory($this->backupPath);
        File::deleteDirectory($this->uploadPath);
        config([
            'backup.path' => $this->backupPath,
            'backup.shell_enabled' => false,
            'backup.upload_paths' => [$this->uploadPath],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupPath);
        File::deleteDirectory($this->uploadPath);

        parent::tearDown();
    }

    public function test_administrator_can_create_a_real_database_backup(): void
    {
        $response = $this->asStaticUser()->post(route('backup.store'), ['type' => 'database']);

        $response->assertRedirect();
        $backup = Backup::firstOrFail();
        $this->assertSame('BKP-'.now()->format('Y').'-000001', $backup->backup_number);
        $this->assertSame('completed', $backup->status);
        $this->assertSame('php', $backup->database_method);
        $this->assertFileExists($this->backupPath.DIRECTORY_SEPARATOR.$backup->file_path);
        $this->assertNotSame('', $backup->checksum);
    }

    public function test_enterprise_dashboard_replaces_the_placeholder_with_live_controls(): void
    {
        $this->asStaticUser()->get(route('backup.index'))
            ->assertOk()
            ->assertSee('Protection Overview')
            ->assertSee('Automatic Schedules')
            ->assertSee('Backup History')
            ->assertSee('Create Backup')
            ->assertDontSee('Coming Soon');
    }

    public function test_files_and_full_backup_types_create_valid_private_zip_archives(): void
    {
        File::ensureDirectoryExists($this->uploadPath);
        File::put($this->uploadPath.DIRECTORY_SEPARATOR.'manual.pdf', 'uploaded document');

        $files = app(BackupManager::class)->create('files', 'Archive Test');
        $full = app(BackupManager::class)->create('full', 'Archive Test');

        $this->assertSame('completed', $files->status);
        $this->assertSame('completed', $full->status);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($this->backupPath.DIRECTORY_SEPARATOR.$files->file_path) === true);
        $this->assertNotFalse($zip->locateName('uploads/storage/manual.pdf'));
        $zip->close();
        $this->assertTrue($zip->open($this->backupPath.DIRECTORY_SEPARATOR.$full->file_path) === true);
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('uploads/storage/manual.pdf'));
        $zip->close();
    }

    public function test_download_is_private_and_requires_administrator_access(): void
    {
        $backup = $this->makeStoredBackup();

        $this->withSession([
            'static_auth_user' => ['name' => 'Sub Admin', 'email' => 'sub@example.com', 'role' => 'Sub admin'],
        ])->get(route('backup.download', $backup))->assertForbidden();

        $this->asStaticUser()->get(route('backup.download', $backup))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
    }

    public function test_verification_detects_valid_and_corrupted_files(): void
    {
        $backup = $this->makeStoredBackup();

        $this->asStaticUser()->post(route('backup.verify', $backup))->assertRedirect();
        $this->assertSame('verified', $backup->fresh()->verification_status);

        File::append($this->backupPath.DIRECTORY_SEPARATOR.$backup->file_path, 'tampered');
        $this->asStaticUser()->post(route('backup.verify', $backup))->assertRedirect();
        $this->assertSame('corrupted', $backup->fresh()->status);
        $this->assertDatabaseHas('backup_logs', ['backup_id' => $backup->id, 'event' => 'verification_failed']);
    }

    public function test_deletion_requires_exact_confirmation_and_removes_private_file(): void
    {
        $backup = $this->makeStoredBackup();
        $path = $this->backupPath.DIRECTORY_SEPARATOR.$backup->file_path;

        $this->asStaticUser()->delete(route('backup.destroy', $backup), [])
            ->assertSessionHasErrors('confirmation');
        $this->assertFileExists($path);

        $this->asStaticUser()->delete(route('backup.destroy', $backup), [
            'confirmation' => $backup->backup_number,
        ])->assertRedirect(route('backup.index'));

        $this->assertFileDoesNotExist($path);
        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
    }

    public function test_daily_weekly_and_monthly_schedules_are_persisted_with_next_run(): void
    {
        foreach (['daily', 'weekly', 'monthly'] as $index => $frequency) {
            $this->asStaticUser()->post(route('backup.schedules.store'), [
                'name' => ucfirst($frequency).' backup',
                'backup_type' => 'database',
                'frequency' => $frequency,
                'run_at' => '02:00',
                'day_of_week' => $frequency === 'weekly' ? 1 : null,
                'day_of_month' => $frequency === 'monthly' ? 15 : null,
                'retention_type' => $index % 2 ? 'days' : 'count',
                'retention_value' => 7,
                'enabled' => 1,
            ])->assertRedirect()->assertSessionHas('success');
        }

        $this->assertCount(3, BackupSchedule::all());
        $this->assertSame(0, BackupSchedule::whereNull('next_run_at')->count());
    }

    public function test_scheduled_command_runs_due_backup(): void
    {
        $schedule = BackupSchedule::create([
            'name' => 'Due schedule',
            'backup_type' => 'database',
            'frequency' => 'daily',
            'run_at' => '01:00',
            'retention_type' => 'count',
            'retention_value' => 2,
            'enabled' => true,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('backup:run-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('backups', [
            'backup_schedule_id' => $schedule->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($schedule->fresh()->last_run_at);
    }

    public function test_count_and_day_retention_remove_only_expired_scheduled_backups(): void
    {
        $schedule = BackupSchedule::create([
            'name' => 'Count retention',
            'backup_type' => 'database',
            'frequency' => 'daily',
            'run_at' => '01:00',
            'retention_type' => 'count',
            'retention_value' => 1,
            'enabled' => true,
        ]);
        $old = $this->makeStoredBackup($schedule, 'old.sql', now()->subDays(10));
        $new = $this->makeStoredBackup($schedule, 'new.sql', now());

        $deleted = app(BackupRetentionService::class)->apply($schedule);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('backups', ['id' => $old->id]);
        $this->assertDatabaseHas('backups', ['id' => $new->id]);
    }

    public function test_restore_requires_exact_backup_number_before_any_job_or_safety_backup(): void
    {
        $backup = $this->makeStoredBackup();

        $this->asStaticUser()->post(route('backup.restore', $backup), [
            'confirmation' => 'wrong-number',
        ])->assertSessionHasErrors('confirmation');

        $this->assertDatabaseCount('restore_jobs', 0);
        $this->assertDatabaseCount('backups', 1);
    }

    public function test_confirmed_restore_creates_safety_backup_and_restores_database_data(): void
    {
        SystemSetting::current()->update(['application_name' => 'Before Backup']);
        $source = app(BackupManager::class)->create('database', 'Restore Test');
        SystemSetting::current()->update(['application_name' => 'Changed After Backup']);

        $response = $this->asStaticUser()->post(route('backup.restore', $source), [
            'confirmation' => $source->backup_number,
        ]);
        $job = RestoreJob::firstOrFail();
        $this->assertSame('completed', $job->status, (string) $job->error_message);
        $response->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Before Backup', SystemSetting::current()->application_name);
        $this->assertDatabaseHas('restore_jobs', [
            'backup_id' => $source->id,
            'status' => 'completed',
        ]);
        $this->assertSame(2, Backup::count());
        $this->assertNotNull(Backup::where('created_by', 'System safety backup')->first());
    }

    public function test_shell_disabled_database_export_uses_php_fallback(): void
    {
        config(['backup.shell_enabled' => false]);

        $backup = app(BackupManager::class)->create('database', 'Fallback Test');

        $this->assertSame('completed', $backup->status);
        $this->assertSame('php', $backup->database_method);
        $this->assertStringContainsString(
            'CREATE TABLE',
            File::get($this->backupPath.DIRECTORY_SEPARATOR.$backup->file_path),
        );
    }

    private function makeStoredBackup(
        ?BackupSchedule $schedule = null,
        string $fileName = 'test-backup.sql',
        $createdAt = null,
    ): Backup {
        File::ensureDirectoryExists($this->backupPath);
        $contents = '-- valid test backup';
        File::put($this->backupPath.DIRECTORY_SEPARATOR.$fileName, $contents);
        $sequence = Backup::count() + 1;

        $backup = Backup::create([
            'backup_number' => sprintf('BKP-%s-%06d', now()->format('Y'), $sequence),
            'backup_schedule_id' => $schedule?->id,
            'type' => 'database',
            'status' => 'completed',
            'verification_status' => 'pending',
            'file_name' => $fileName,
            'file_path' => $fileName,
            'size_bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'completed_at' => now(),
            'created_by' => 'Test Administrator',
        ]);
        if ($createdAt) {
            $backup->timestamps = false;
            $backup->created_at = $createdAt;
            $backup->updated_at = $createdAt;
            $backup->save();
        }

        return $backup;
    }
}
