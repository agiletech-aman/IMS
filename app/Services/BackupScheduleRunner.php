<?php

namespace App\Services;

use App\Models\BackupSchedule;

class BackupScheduleRunner
{
    public function __construct(
        private readonly BackupManager $manager,
        private readonly BackupRetentionService $retention,
    ) {}

    public function runDue(): array
    {
        $completed = 0;
        $failed = 0;

        BackupSchedule::where('enabled', true)
            ->where(fn ($query) => $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->orderBy('id')
            ->get()
            ->each(function (BackupSchedule $schedule) use (&$completed, &$failed): void {
                $backup = $this->manager->create(
                    $schedule->backup_type,
                    'Scheduled task',
                    schedule: $schedule,
                    metadata: ['schedule_name' => $schedule->name],
                );

                $schedule->update([
                    'last_run_at' => now(),
                    'next_run_at' => $schedule->calculateNextRun(now()),
                ]);
                $this->retention->apply($schedule);

                in_array($backup->status, ['completed', 'verified'], true) ? $completed++ : $failed++;
            });

        return compact('completed', 'failed');
    }
}
