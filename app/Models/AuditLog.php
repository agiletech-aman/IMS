<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use CentreScoped;

    protected $fillable = [
        'centre',
        'actor_name',
        'actor_email',
        'actor_role',
        'action',
        'module',
        'description',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'route_name',
        'request_method',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Plain-text summary of a status/assigned_to change captured on this
     * entry, e.g. for CSV/XLSX export where HTML markup doesn't apply.
     */
    public function describeStatusAssignmentChange(): string
    {
        $newValues = $this->new_values ?? [];
        $oldValues = $this->old_values ?? [];
        $parts = [];

        if (array_key_exists('status', $newValues)) {
            $parts[] = 'Status changed from '.($oldValues['status'] ?? '—').' to '.$newValues['status'];
        }

        if (array_key_exists('assigned_to', $newValues)) {
            $parts[] = $newValues['assigned_to']
                ? 'Assigned to '.$newValues['assigned_to']
                : 'Unassigned from '.($oldValues['assigned_to'] ?? 'previous user');
        }

        return implode('; ', $parts);
    }
}
