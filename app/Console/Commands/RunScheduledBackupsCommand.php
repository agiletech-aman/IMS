<?php

namespace App\Console\Commands;

use App\Services\BackupScheduleRunner;
use Illuminate\Console\Command;

class RunScheduledBackupsCommand extends Command
{
    protected $signature = 'backup:run-scheduled';

    protected $description = 'Run due automatic backup schedules and apply their retention policies';

    public function handle(BackupScheduleRunner $runner): int
    {
        $result = $runner->runDue();
        $this->info("Scheduled backups complete: {$result['completed']} succeeded, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
