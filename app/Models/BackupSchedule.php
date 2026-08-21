<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupSchedule extends Model
{
    use CentreScoped;

    protected $fillable = [
        'name', 'backup_type', 'frequency', 'run_at', 'day_of_week',
        'day_of_month', 'retention_type', 'retention_value', 'enabled',
        'last_run_at', 'next_run_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'retention_value' => 'integer',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function calculateNextRun(?CarbonInterface $from = null): CarbonInterface
    {
        $from = ($from ?: now())->copy();
        [$hour, $minute] = array_map('intval', explode(':', substr($this->run_at, 0, 5)));

        if ($this->frequency === 'daily') {
            $candidate = $from->copy()->setTime($hour, $minute);

            return $candidate->isAfter($from) ? $candidate : $candidate->addDay();
        }

        if ($this->frequency === 'weekly') {
            $candidate = $from->copy()->startOfDay();
            $daysAhead = ((int) $this->day_of_week - (int) $candidate->dayOfWeek + 7) % 7;
            $candidate->addDays($daysAhead)->setTime($hour, $minute);

            return $candidate->isAfter($from) ? $candidate : $candidate->addWeek();
        }

        $candidate = $from->copy()->startOfMonth();
        $candidate->day(min((int) $this->day_of_month, $candidate->daysInMonth))->setTime($hour, $minute);
        if (! $candidate->isAfter($from)) {
            $candidate->addMonthNoOverflow()->startOfMonth();
            $candidate->day(min((int) $this->day_of_month, $candidate->daysInMonth))->setTime($hour, $minute);
        }

        return $candidate;
    }
}
