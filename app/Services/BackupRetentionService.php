<?php

namespace App\Services;

use App\Models\BackupSchedule;
use Throwable;

class BackupRetentionService
{
    public function __construct(private readonly BackupStorage $storage) {}

    public function apply(BackupSchedule $schedule): int
    {
        $query = $schedule->backups()
            ->whereIn('status', ['completed', 'verified', 'corrupted'])
            ->whereDoesntHave('restoreJobs')
            ->whereDoesntHave('safetyRestoreJobs');

        $expired = $schedule->retention_type === 'days'
            ? (clone $query)->where('created_at', '<', now()->subDays($schedule->retention_value))->get()
            : (clone $query)->latest('created_at')->skip($schedule->retention_value)->take(PHP_INT_MAX)->get();

        $deleted = 0;
        foreach ($expired as $backup) {
            try {
                if (filled($backup->file_path)) {
                    $this->storage->deleteIfExists($backup->file_path);
                }
                $backup->delete();
                $deleted++;
            } catch (Throwable $exception) {
                report($exception);
                $backup->logs()->create([
                    'level' => 'error',
                    'event' => 'retention_failed',
                    'message' => mb_strimwidth($exception->getMessage(), 0, 2000),
                ]);
            }
        }

        return $deleted;
    }

    public function applyAll(): int
    {
        return BackupSchedule::where('enabled', true)
            ->get()
            ->sum(fn (BackupSchedule $schedule) => $this->apply($schedule));
    }
}
