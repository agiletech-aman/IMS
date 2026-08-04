<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupSchedule;
use Throwable;

class BackupHealthService
{
    public function __construct(private readonly BackupStorage $storage) {}

    public function status(): array
    {
        $issues = [];
        $level = 'healthy';

        try {
            $root = $this->storage->prepare();
            $freeBytes = disk_free_space($root);
            if ($freeBytes !== false && $freeBytes < config('backup.health.minimum_free_space_mb', 100) * 1024 * 1024) {
                $issues[] = 'Backup storage is running low on free space.';
                $level = 'critical';
            }
        } catch (Throwable $exception) {
            $issues[] = $exception->getMessage();
            $level = 'critical';
        }

        $lastSuccessful = Backup::whereIn('status', ['completed', 'verified'])->latest('completed_at')->first();
        $maximumAge = (int) config('backup.health.maximum_age_hours', 72);
        if (! $lastSuccessful) {
            $issues[] = 'No successful backup has been created yet.';
            $level = $level === 'critical' ? $level : 'warning';
        } elseif ($lastSuccessful->completed_at?->lt(now()->subHours($maximumAge))) {
            $issues[] = "Last successful backup is older than {$maximumAge} hours.";
            $level = $level === 'critical' ? $level : 'warning';
        }

        $corrupted = Backup::where('status', 'corrupted')->count();
        if ($corrupted > 0) {
            $issues[] = "{$corrupted} corrupted backup(s) require attention.";
            $level = 'critical';
        }

        $recentFailures = Backup::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        if ($recentFailures > 0) {
            $issues[] = "{$recentFailures} backup(s) failed in the last 24 hours.";
            $level = $level === 'critical' ? $level : 'warning';
        }

        return [
            'level' => $level,
            'label' => match ($level) {
                'healthy' => 'Healthy',
                'warning' => 'Attention needed',
                default => 'Critical',
            },
            'issues' => $issues,
            'last_successful' => $lastSuccessful,
            'next_scheduled' => BackupSchedule::where('enabled', true)->whereNotNull('next_run_at')->min('next_run_at'),
        ];
    }
}
