<?php

use App\Models\Asset;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:check-expiries', function (NotificationService $notifications) {
    NotificationPreference::seedDefaults();

    foreach ([
        'amc_expiry' => ['AMC', 'amc_expiry'],
        'warranty_expiry' => ['Warranty', 'warranty_expiry'],
    ] as $eventType => [$label, $column]) {
        $preference = NotificationPreference::where('event_type', $eventType)->firstOrFail();
        $days = $preference->days_before ?? 30;

        Asset::whereNotNull($column)
            ->whereDate($column, '>=', today())
            ->whereDate($column, '<=', today()->addDays($days))
            ->each(function (Asset $asset) use ($notifications, $eventType, $label, $column): void {
                $expiry = $asset->{$column};
                $daysLeft = today()->diffInDays($expiry, false);
                $notifications->send(
                    $eventType,
                    "{$label} expiring soon",
                    "{$label} for {$asset->asset_tag} — {$asset->name} expires on {$expiry->format('d M Y')} ({$daysLeft} days remaining).",
                    $daysLeft <= 7 ? 'critical' : 'warning',
                    'AMC & Warranty',
                    ['asset_id' => $asset->id, 'expiry_date' => $expiry->format('Y-m-d')],
                    "{$eventType}:{$asset->id}:{$expiry->format('Y-m-d')}",
                );
            });
    }

    $this->info('AMC and warranty expiry alerts checked.');
})->purpose('Create in-app and email alerts for upcoming AMC and warranty expiries');

Artisan::command('storage:prepare-public', function () {
    if (! config('filesystems.public_direct')) {
        $this->warn('PUBLIC_DISK_DIRECT is disabled. Use storage:link when symlinks are available.');

        return Command::FAILURE;
    }

    $source = storage_path('app/public');
    $destination = config('filesystems.disks.public.root');

    File::ensureDirectoryExists($destination);
    if (File::isDirectory($source)) {
        File::copyDirectory($source, $destination);
    }
    foreach (['.htaccess', 'index.html'] as $safetyFile) {
        $template = public_path("uploads/{$safetyFile}");
        if (File::isFile($template) && ! File::isFile("{$destination}/{$safetyFile}")) {
            File::copy($template, "{$destination}/{$safetyFile}");
        }
    }

    $this->info("Public upload directory is ready: {$destination}");
    $this->line('Existing files from storage/app/public were copied when available.');

    return Command::SUCCESS;
})->purpose('Prepare direct public uploads when shared hosting does not support symlinks');

Schedule::command('notifications:check-expiries')->dailyAt('08:00');
Schedule::command('backup:run-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('backup:health-check')->dailyAt('07:30')->withoutOverlapping();
