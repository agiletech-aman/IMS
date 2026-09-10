<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    use CentreScoped;

    protected $fillable = ['backup_id', 'level', 'event', 'message', 'context'];

    protected static function centreOptional(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }
}
