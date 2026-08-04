<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupVerifier;
use Illuminate\Console\Command;

class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify {backup? : Backup number} {--all : Verify every completed backup}';

    protected $description = 'Verify backup checksums and ZIP integrity';

    public function handle(BackupVerifier $verifier): int
    {
        if (! $this->option('all') && ! $this->argument('backup')) {
            $this->error('Provide a backup number or use --all.');

            return self::INVALID;
        }

        $query = Backup::whereIn('status', ['completed', 'verified']);
        if (! $this->option('all')) {
            $query->where('backup_number', $this->argument('backup'));
        }
        $backups = $query->get();
        if ($backups->isEmpty()) {
            $this->error('No matching completed backups found.');

            return self::FAILURE;
        }

        $failed = 0;
        foreach ($backups as $backup) {
            if ($verifier->verify($backup)) {
                $this->info("{$backup->backup_number}: verified");
            } else {
                $this->error("{$backup->backup_number}: corrupted");
                $failed++;
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
