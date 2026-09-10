<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Backup extends Model
{
    use CentreScoped;

    public const TYPES = ['database', 'files', 'full'];

    public const STATUSES = ['queued', 'running', 'completed', 'failed', 'verified', 'corrupted'];

    protected static function centreOptional(): bool
    {
        return true;
    }

    protected $fillable = [
        'backup_number', 'backup_schedule_id', 'type', 'status',
        'verification_status', 'file_name', 'file_path', 'size_bytes',
        'duration_seconds', 'checksum', 'database_method', 'error_message',
        'metadata', 'created_by', 'created_by_email', 'started_at',
        'completed_at', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BackupSchedule::class, 'backup_schedule_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    public function restoreJobs(): HasMany
    {
        return $this->hasMany(RestoreJob::class);
    }

    public function safetyRestoreJobs(): HasMany
    {
        return $this->hasMany(RestoreJob::class, 'safety_backup_id');
    }

    public function isDownloadable(): bool
    {
        return in_array($this->status, ['completed', 'verified'], true)
            && filled($this->file_path);
    }

    public function formattedSize(): string
    {
        $bytes = max(0, $this->size_bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2).' '.$units[$power];
    }
}
