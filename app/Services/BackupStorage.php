<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupStorage
{
    public function root(): string
    {
        return rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
    }

    public function prepare(): string
    {
        $root = $this->root();
        File::ensureDirectoryExists($root, 0700, true);

        if (! File::exists($root.DIRECTORY_SEPARATOR.'.htaccess')) {
            File::put($root.DIRECTORY_SEPARATOR.'.htaccess', "Require all denied\nDeny from all\n");
        }
        if (! File::exists($root.DIRECTORY_SEPARATOR.'index.html')) {
            File::put($root.DIRECTORY_SEPARATOR.'index.html', '');
        }

        if (! is_writable($root)) {
            throw new RuntimeException("Backup directory is not writable: {$root}");
        }

        return $root;
    }

    public function pathForNewFile(string $fileName): string
    {
        $this->assertSafeFileName($fileName);

        return $this->prepare().DIRECTORY_SEPARATOR.$fileName;
    }

    public function resolveExisting(string $relativePath): string
    {
        $this->assertSafeFileName($relativePath);
        $root = realpath($this->prepare());
        $path = realpath($this->root().DIRECTORY_SEPARATOR.$relativePath);

        if ($root === false || $path === false || ! is_file($path)) {
            throw new RuntimeException('The private backup file is missing.');
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Invalid backup file path.');
        }

        return $path;
    }

    public function delete(string $relativePath): void
    {
        $path = $this->resolveExisting($relativePath);
        if (! File::delete($path)) {
            throw new RuntimeException('The backup file could not be deleted.');
        }
    }

    public function deleteIfExists(string $relativePath): void
    {
        $this->assertSafeFileName($relativePath);
        $candidate = $this->root().DIRECTORY_SEPARATOR.$relativePath;
        if (! file_exists($candidate)) {
            return;
        }

        $this->delete($relativePath);
    }

    private function assertSafeFileName(string $fileName): void
    {
        if (
            blank($fileName)
            || basename($fileName) !== $fileName
            || str_contains($fileName, "\0")
            || str_contains($fileName, '..')
            || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $fileName)
        ) {
            throw new RuntimeException('Invalid backup file name.');
        }
    }
}
