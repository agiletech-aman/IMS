<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\RestoreJob;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

class RestoreManager
{
    public function __construct(
        private readonly BackupStorage $storage,
        private readonly BackupVerifier $verifier,
        private readonly BackupManager $backups,
        private readonly DatabaseRestoreService $database,
        private readonly AuditLogger $audit,
    ) {}

    public function restore(Backup $backup, string $requestedBy, ?string $email = null): RestoreJob
    {
        if (! in_array($backup->type, ['database', 'full'], true) || ! $backup->isDownloadable()) {
            throw new RuntimeException('Only completed database or full backups can be restored.');
        }

        $job = RestoreJob::create([
            'backup_id' => $backup->id,
            'status' => 'queued',
            'requested_by' => $requestedBy,
            'requested_by_email' => $email,
        ]);
        $temporarySql = null;

        try {
            if (! $this->verifier->verify($backup)) {
                throw new RuntimeException('Restore stopped because backup verification failed.');
            }

            $safety = $this->backups->create(
                'database',
                'System safety backup',
                metadata: ['safety_for_restore_job' => $job->id, 'restore_source' => $backup->backup_number],
            );
            if (! in_array($safety->status, ['completed', 'verified'], true)) {
                throw new RuntimeException('Restore stopped because the safety backup could not be created.');
            }

            $job->update([
                'safety_backup_id' => $safety->id,
                'status' => 'running',
                'started_at' => now(),
            ]);
            $sqlPath = $this->storage->resolveExisting((string) $backup->file_path);

            if ($backup->type === 'full') {
                $zip = new ZipArchive;
                if ($zip->open($sqlPath) !== true) {
                    throw new RuntimeException('Unable to open the full backup archive.');
                }
                $contents = $zip->getFromName('database.sql');
                $zip->close();
                if ($contents === false) {
                    throw new RuntimeException('Full backup does not contain database.sql.');
                }
                $temporarySql = $this->storage->pathForNewFile('restore-'.$job->id.'-'.bin2hex(random_bytes(6)).'.sql');
                File::put($temporarySql, $contents);
                $sqlPath = $temporarySql;
            }

            $method = $this->database->import($sqlPath);
            $job->update(['status' => 'completed', 'completed_at' => now(), 'error_message' => null]);
            $backup->logs()->create([
                'level' => 'success',
                'event' => 'restore_completed',
                'message' => "Database restored using {$method}. Safety backup: {$safety->backup_number}.",
                'context' => ['restore_job_id' => $job->id, 'safety_backup_id' => $safety->id],
            ]);
            $this->audit->record(
                'RESTORE',
                'Backup & Recovery',
                "Database restored from {$backup->backup_number}; safety backup {$safety->backup_number} was created.",
                $backup,
                metadata: ['restore_job_id' => $job->id, 'safety_backup' => $safety->backup_number],
            );
        } catch (Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => mb_strimwidth($exception->getMessage(), 0, 2000),
            ]);
            $backup->logs()->create([
                'level' => 'error',
                'event' => 'restore_failed',
                'message' => $job->error_message,
                'context' => ['restore_job_id' => $job->id],
            ]);
            $this->audit->record(
                'RESTORE',
                'Backup & Recovery',
                "Restore from {$backup->backup_number} failed.",
                $backup,
                result: 'Failed',
                metadata: ['restore_job_id' => $job->id, 'error' => $job->error_message],
            );
        } finally {
            if ($temporarySql) {
                File::delete($temporarySql);
            }
        }

        return $job->fresh(['backup', 'safetyBackup']);
    }
}
