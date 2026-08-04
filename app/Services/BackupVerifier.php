<?php

namespace App\Services;

use App\Models\Backup;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupVerifier
{
    public function __construct(private readonly BackupStorage $storage) {}

    public function verify(Backup $backup): bool
    {
        try {
            $path = $this->storage->resolveExisting((string) $backup->file_path);
            if (filesize($path) < 1) {
                throw new RuntimeException('Backup file is empty.');
            }
            if (! hash_equals((string) $backup->checksum, hash_file('sha256', $path))) {
                throw new RuntimeException('Checksum mismatch detected.');
            }

            if (in_array($backup->type, ['files', 'full'], true)) {
                if (! class_exists(ZipArchive::class)) {
                    throw new RuntimeException('ZipArchive is unavailable.');
                }
                $zip = new ZipArchive;
                if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
                    throw new RuntimeException('ZIP archive integrity check failed.');
                }
                if ($backup->type === 'full' && $zip->locateName('database.sql') === false) {
                    $zip->close();
                    throw new RuntimeException('Full backup does not contain database.sql.');
                }
                $zip->close();
            }

            $backup->update([
                'status' => 'verified',
                'verification_status' => 'verified',
                'verified_at' => now(),
                'error_message' => null,
            ]);
            $backup->logs()->create([
                'level' => 'success',
                'event' => 'verification_completed',
                'message' => 'Checksum and archive integrity verification completed successfully.',
            ]);

            return true;
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'corrupted',
                'verification_status' => 'corrupted',
                'verified_at' => now(),
                'error_message' => mb_strimwidth($exception->getMessage(), 0, 2000),
            ]);
            $backup->logs()->create([
                'level' => 'error',
                'event' => 'verification_failed',
                'message' => mb_strimwidth($exception->getMessage(), 0, 2000),
            ]);

            return false;
        }
    }
}
