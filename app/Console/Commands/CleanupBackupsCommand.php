<?php

namespace App\Console\Commands;

use App\Services\BackupRetentionService;
use Illuminate\Console\Command;

class CleanupBackupsCommand extends Command
{
    protected $signature = 'backup:cleanup';

    protected $description = 'Apply count/day retention rules to scheduled backups';

    public function handle(BackupRetentionService $retention): int
    {
        $deleted = $retention->applyAll();
        $this->info("Retention cleanup removed {$deleted} backup(s).");

        return self::SUCCESS;
    }
}
