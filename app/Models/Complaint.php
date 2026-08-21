<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use CentreScoped;

    public const STATUSES = [
        'Complaint Raised',
        'Engineer Assigned',
        'Engineer Visit',
        'Work in Progress',
        'Resolved',
        'Closed',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    protected $fillable = [
        'centre',
        'complaint_number', 'subject', 'description', 'requester_name',
        'requester_email', 'requester_contact', 'category', 'priority',
        'asset_id', 'engineer_id', 'status', 'assigned_at',
        'visit_scheduled_at', 'visit_started_at', 'work_started_at',
        'resolved_at', 'closed_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'visit_scheduled_at' => 'datetime',
            'visit_started_at' => 'datetime',
            'work_started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ComplaintActivity::class)->oldest();
    }

    public function nextStatus(): ?string
    {
        $position = array_search($this->status, self::STATUSES, true);

        return $position === false ? null : (self::STATUSES[$position + 1] ?? null);
    }
}
