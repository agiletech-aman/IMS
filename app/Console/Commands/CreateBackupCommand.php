<?php

namespace App\Console\Commands;

use App\Services\BackupManager;
use Illuminate\Console\Command;

class CreateBackupCommand extends Command
{
    protected $signature = 'backup:create
        {type=full : database, files, or full}
        {--created-by=Artisan : Actor recorded in backup history}';

    protected $description = 'Create a private database, uploaded files, or full backup';

    public function handle(BackupManager $manager): int
    {
        $backup = $manager->create((string) $this->argument('type'), (string) $this->option('created-by'));

        if ($backup->status === 'failed') {
            $this->error("{$backup->backup_number} failed: {$backup->error_message}");

            return self::FAILURE;
        }

        $this->info("{$backup->backup_number} completed ({$backup->formattedSize()}).");

        return self::SUCCESS;
    }
}
