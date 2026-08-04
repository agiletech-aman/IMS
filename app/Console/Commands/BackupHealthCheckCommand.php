<?php

namespace App\Console\Commands;

use App\Services\BackupHealthService;
use Illuminate\Console\Command;

class BackupHealthCheckCommand extends Command
{
    protected $signature = 'backup:health-check';

    protected $description = 'Check backup freshness, integrity alerts, storage writability, and free space';

    public function handle(BackupHealthService $health): int
    {
        $status = $health->status();
        $this->line("Backup health: {$status['label']}");
        foreach ($status['issues'] as $issue) {
            $this->warn($issue);
        }

        return $status['level'] === 'critical' ? self::FAILURE : self::SUCCESS;
    }
}
