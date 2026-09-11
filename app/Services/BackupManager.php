<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupManager
{
    public function __construct(
        private readonly BackupStorage $storage,
        private readonly DatabaseBackupService $database,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function create(
        string $type,
        ?string $createdBy = null,
        ?string $createdByEmail = null,
        ?BackupSchedule $schedule = null,
        array $metadata = [],
    ): Backup {
        if (! in_array($type, Backup::TYPES, true)) {
            throw new RuntimeException('Unsupported backup type.');
        }

        $backup = $this->createRecord($type, $createdBy, $createdByEmail, $schedule, $metadata);
        $started = microtime(true);
        $backup->update(['status' => 'running', 'started_at' => now()]);
        $this->log($backup, 'info', 'backup_started', ucfirst($type).' backup started.');
        $destination = null;

        try {
            $extension = $type === 'database' ? 'sql' : 'zip';
            $fileName = strtolower($backup->backup_number).'-'.$type.'.'.$extension;
            $destination = $this->storage->pathForNewFile($fileName);
            $databaseMethod = null;

            if ($type === 'database') {
                $databaseMethod = $this->database->export($destination);
                $this->log($backup, 'info', 'database_exported', "Database exported using {$databaseMethod}.");
            } else {
                if (! class_exists(ZipArchive::class)) {
                    throw new RuntimeException('ZipArchive is not installed. Files and full backups are unavailable on this server.');
                }
                $databaseMethod = $this->createArchive($destination, $type === 'full', $backup);
            }

            clearstatcache(true, $destination);
            $backup->update([
                'status' => 'completed',
                'verification_status' => 'pending',
                'file_name' => $fileName,
                'file_path' => $fileName,
                'size_bytes' => filesize($destination),
                'duration_seconds' => max(0, (int) round(microtime(true) - $started)),
                'checksum' => hash_file('sha256', $destination),
                'database_method' => $databaseMethod,
                'completed_at' => now(),
            ]);
            $this->log($backup, 'success', 'backup_completed', 'Backup completed successfully.', [
                'size_bytes' => $backup->size_bytes,
            ]);
            $this->audit->record(
                'CREATE',
                'Backup & Recovery',
                "{$backup->backup_number} {$type} backup completed.",
                $backup,
                metadata: ['type' => $type, 'size_bytes' => $backup->size_bytes],
            );
            $this->notifySafely(
                'backup_completed',
                'Backup completed',
                "{$backup->backup_number} {$type} backup completed successfully.",
                'success',
                $backup,
            );
        } catch (Throwable $exception) {
            if ($destination && is_file($destination)) {
                File::delete($destination);
            }
            $backup->update([
                'status' => 'failed',
                'duration_seconds' => max(0, (int) round(microtime(true) - $started)),
                'error_message' => mb_strimwidth($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            $this->log($backup, 'error', 'backup_failed', $backup->error_message);
            $this->audit->record(
                'CREATE',
                'Backup & Recovery',
                "{$backup->backup_number} {$type} backup failed.",
                $backup,
                result: 'Failed',
                metadata: ['error' => $backup->error_message],
            );
            $this->notifySafely(
                'backup_failed',
                'Backup failed',
                "{$backup->backup_number} failed: {$backup->error_message}",
                'critical',
                $backup,
            );
        }

        return $backup->fresh(['logs']);
    }

    private function createRecord(
        string $type,
        ?string $createdBy,
        ?string $createdByEmail,
        ?BackupSchedule $schedule,
        array $metadata,
    ): Backup {
        return DB::transaction(function () use ($type, $createdBy, $createdByEmail, $schedule, $metadata): Backup {
            $year = now()->format('Y');
            $last = Backup::where('backup_number', 'like', "BKP-{$year}-%")
                ->orderByDesc('backup_number')
                ->lockForUpdate()
                ->value('backup_number');
            $sequence = $last ? ((int) substr($last, -6)) + 1 : 1;

            return Backup::create([
                'backup_number' => sprintf('BKP-%s-%06d', $year, $sequence),
                'backup_schedule_id' => $schedule?->id,
                'type' => $type,
                'status' => 'queued',
                'created_by' => $createdBy ?: 'System',
                'created_by_email' => $createdByEmail,
                'metadata' => $metadata ?: null,
            ]);
        }, 3);
    }

    private function createArchive(string $destination, bool $includeDatabase, Backup $backup): ?string
    {
        $zip = new ZipArchive;
        $result = $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException("Unable to create ZIP archive (error {$result}).");
        }

        $databaseMethod = null;
        $temporarySql = null;
        try {
            if ($includeDatabase) {
                $temporarySql = $this->storage->pathForNewFile('work-'.$backup->id.'-'.bin2hex(random_bytes(6)).'.sql');
                $databaseMethod = $this->database->export($temporarySql);
                if (! $zip->addFile($temporarySql, 'database.sql')) {
                    throw new RuntimeException('Unable to add the database export to the full backup.');
                }
                $this->log($backup, 'info', 'database_exported', "Database exported using {$databaseMethod}.");
            }

            $fileCount = $this->addUploads($zip);
            $zip->addFromString('backup-manifest.json', json_encode([
                'backup_number' => $backup->backup_number,
                'type' => $backup->type,
                'created_at' => now()->toIso8601String(),
                'file_count' => $fileCount,
                'database_method' => $databaseMethod,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->log($backup, 'info', 'files_archived', "{$fileCount} uploaded files added to the archive.");
        } finally {
            $zip->close();
            if ($temporarySql) {
                File::delete($temporarySql);
            }
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('ZIP archive was not created successfully.');
        }

        return $databaseMethod;
    }

    private function addUploads(ZipArchive $zip): int
    {
        $count = 0;
        $seenRoots = [];

        foreach ((array) config('backup.upload_paths', []) as $index => $configuredRoot) {
            $root = realpath($configuredRoot);
            if ($root === false || ! is_dir($root) || isset($seenRoots[$root])) {
                continue;
            }
            $seenRoots[$root] = true;
            $prefix = $index === 0 ? 'uploads/storage' : 'uploads/public';
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $path = $file->getRealPath();
                if ($path === false || ! str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
                if ($this->excludedUploadPath($relative)) {
                    continue;
                }
                if (! $zip->addFile($path, $prefix.'/'.$relative)) {
                    throw new RuntimeException("Unable to add an uploaded file to the archive: {$relative}");
                }
                $count++;
            }
        }

        return $count;
    }

    private function excludedUploadPath(string $relative): bool
    {
        $segments = array_map('strtolower', explode('/', str_replace('\\', '/', $relative)));
        $blocked = ['.env', 'vendor', 'node_modules', 'cache', 'logs', 'backups'];

        return collect($segments)->contains(fn (string $segment) => in_array($segment, $blocked, true))
            || str_starts_with(basename($relative), '.');
    }

    private function log(Backup $backup, string $level, string $event, string $message, array $context = []): void
    {
        $backup->logs()->create([
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }

    private function notifySafely(
        string $eventType,
        string $title,
        string $message,
        string $severity,
        Backup $backup,
    ): void {
        try {
            if (
                ! Schema::hasTable('notification_preferences')
                || ! Schema::hasTable('system_notifications')
            ) {
                return;
            }
            $this->notifications->send(
                $eventType,
                $title,
                $message,
                $severity,
                'Backup & Recovery',
                ['backup_id' => $backup->id, 'backup_number' => $backup->backup_number],
                "{$eventType}:{$backup->id}",
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
