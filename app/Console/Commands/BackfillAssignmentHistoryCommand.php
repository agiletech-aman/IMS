<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetAssignmentHistory;
use App\Models\Faculty;
use Illuminate\Console\Command;

class BackfillAssignmentHistoryCommand extends Command
{
    protected $signature = 'assets:backfill-assignment-history';

    protected $description = 'Create a missing Assignment History record for every asset whose "Assigned To" was set before that history was tracked';

    public function handle(): int
    {
        $assets = Asset::withoutGlobalScopes()
            ->whereNotNull('assigned_to')
            ->where('assigned_to', '!=', '')
            ->get();

        $created = 0;
        $skipped = [];

        foreach ($assets as $asset) {
            $faculty = Faculty::withoutGlobalScopes()->where('name', trim($asset->assigned_to))->first();

            if (! $faculty) {
                $skipped[] = "{$asset->asset_tag} (assigned to \"{$asset->assigned_to}\", no matching user)";

                continue;
            }

            $hasOpenHistory = AssetAssignmentHistory::where('asset_id', $asset->id)
                ->where('faculty_id', $faculty->id)
                ->whereNull('unassigned_at')
                ->exists();

            if ($hasOpenHistory) {
                continue;
            }

            AssetAssignmentHistory::create([
                'faculty_id' => $faculty->id,
                'asset_id' => $asset->id,
                'assigned_at' => $asset->created_at ?? now(),
                'assigned_by' => 'System (backfill)',
            ]);

            $created++;
        }

        $this->info("Backfilled {$created} assignment history record(s).");

        if ($skipped !== []) {
            $this->warn('Skipped (no matching user found for the assigned name):');
            foreach ($skipped as $line) {
                $this->line("  - {$line}");
            }
        }

        return self::SUCCESS;
    }
}
