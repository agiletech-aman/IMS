<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\SubDepartment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rebuilds an asset's full movement path (department / sub-department moves,
 * assignment, reallocation, return and status changes) from the CREATE and
 * UPDATE entries AuditObserver records on every save. Explicit ASSIGN /
 * UNASSIGN entries are skipped because the same change is already captured
 * by the observer's UPDATE entry.
 */
class AssetMovementHistory
{
    private const TRACKED = ['department_id', 'sub_department_id', 'assigned_to', 'status'];

    private ?Collection $logs = null;

    /** @var array<int, string> */
    private array $departmentNames = [];

    /** @var array<int, string> */
    private array $subDepartmentNames = [];

    public function __construct(private readonly Asset $asset)
    {
    }

    public static function for(Asset $asset): self
    {
        return new self($asset);
    }

    /**
     * One row per audit entry that touched a tracked field, newest first.
     * Each row: date, actor, events[] where an event has type, label, from, to.
     */
    public function timeline(): Collection
    {
        return $this->logs()
            ->map(function (AuditLog $log) {
                $events = $log->action === 'CREATE'
                    ? $this->createdEvents($log->new_values ?? [])
                    : $this->updatedEvents($log->old_values ?? [], $log->new_values ?? []);

                return [
                    'date' => $log->created_at,
                    'actor' => $log->actor_name ?? 'System',
                    'events' => $events,
                ];
            })
            ->filter(fn (array $row) => $row['events'] !== [])
            ->reverse()
            ->values();
    }

    /**
     * Department / sub-department stays, newest first. Each row: department,
     * sub_department, from, to (null = current), days.
     */
    public function departmentPeriods(): Collection
    {
        $moves = $this->logs()
            ->filter(fn (AuditLog $log) => $log->action === 'UPDATE')
            ->filter(fn (AuditLog $log) => $this->touches($log->new_values ?? [], ['department_id', 'sub_department_id']))
            ->values();

        // Work out where the asset started: the "old" side of the first move,
        // or its current location when it has never moved.
        $first = $moves->first();
        $department = $first && array_key_exists('department_id', $first->new_values ?? [])
            ? ($first->old_values['department_id'] ?? null)
            : $this->asset->department_id;
        $subDepartment = $first && array_key_exists('sub_department_id', $first->new_values ?? [])
            ? ($first->old_values['sub_department_id'] ?? null)
            : $this->asset->sub_department_id;

        $periods = [];
        $start = $this->asset->created_at;

        foreach ($moves as $log) {
            $periods[] = $this->period($department, $subDepartment, $start, $log->created_at);

            $new = $log->new_values ?? [];
            $department = array_key_exists('department_id', $new) ? $new['department_id'] : $department;
            $subDepartment = array_key_exists('sub_department_id', $new) ? $new['sub_department_id'] : $subDepartment;
            $start = $log->created_at;
        }

        $periods[] = $this->period($department, $subDepartment, $start, null);

        return collect($periods)->reverse()->values();
    }

    /**
     * Plain-text summary of a timeline row's events, for CSV/XLSX export.
     */
    public static function describe(array $events): string
    {
        return collect($events)
            ->map(fn (array $event) => $event['from'] !== null && $event['to'] !== null
                ? "{$event['label']}: {$event['from']} → {$event['to']}"
                : "{$event['label']}: ".($event['to'] ?? $event['from']))
            ->implode('; ');
    }

    private function logs(): Collection
    {
        if ($this->logs !== null) {
            return $this->logs;
        }

        $logs = AuditLog::where('auditable_type', $this->asset->getMorphClass())
            ->where('auditable_id', $this->asset->id)
            ->whereIn('action', ['CREATE', 'UPDATE'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (AuditLog $log) => $log->action === 'CREATE'
                || $this->touches($log->new_values ?? [], self::TRACKED))
            ->values();

        $this->loadNames($logs);

        return $this->logs = $logs;
    }

    private function createdEvents(array $values): array
    {
        $events = [['type' => 'created', 'label' => 'Asset Created', 'from' => null, 'to' => $this->asset->asset_tag]];

        if (! empty($values['department_id'])) {
            $events[] = [
                'type' => 'department',
                'label' => 'Placed in Department',
                'from' => null,
                'to' => $this->location($values['department_id'], $values['sub_department_id'] ?? null),
            ];
        }

        if (filled($values['assigned_to'] ?? null)) {
            $events[] = ['type' => 'assigned', 'label' => 'Assigned', 'from' => null, 'to' => $values['assigned_to']];
        }

        return $events;
    }

    private function updatedEvents(array $old, array $new): array
    {
        $events = [];

        if ($this->touches($new, ['department_id'])) {
            $events[] = [
                'type' => 'department',
                'label' => 'Department Changed',
                'from' => $this->location($old['department_id'] ?? null, $old['sub_department_id'] ?? null),
                'to' => $this->location($new['department_id'], $new['sub_department_id'] ?? $old['sub_department_id'] ?? null),
            ];
        } elseif ($this->touches($new, ['sub_department_id'])) {
            $events[] = [
                'type' => 'department',
                'label' => 'Sub Department Changed',
                'from' => $this->subDepartmentName($old['sub_department_id'] ?? null),
                'to' => $this->subDepartmentName($new['sub_department_id']),
            ];
        }

        if ($this->touches($new, ['assigned_to'])) {
            $from = filled($old['assigned_to'] ?? null) ? $old['assigned_to'] : null;
            $to = filled($new['assigned_to']) ? $new['assigned_to'] : null;

            $events[] = match (true) {
                $from === null && $to !== null => ['type' => 'assigned', 'label' => 'Assigned', 'from' => null, 'to' => $to],
                $from !== null && $to !== null => ['type' => 'reallocated', 'label' => 'Reallocated', 'from' => $from, 'to' => $to],
                default => ['type' => 'returned', 'label' => 'Returned', 'from' => $from ?? 'previous user', 'to' => null],
            };
        }

        if ($this->touches($new, ['status'])) {
            $events[] = ['type' => 'status', 'label' => 'Status Changed', 'from' => $old['status'] ?? '—', 'to' => $new['status']];
        }

        return $events;
    }

    private function period($department, $subDepartment, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return [
            'department' => $this->departmentName($department),
            'sub_department' => $this->subDepartmentName($subDepartment),
            'from' => $from,
            'to' => $to,
            'days' => $from ? (int) $from->diffInDays($to ?? now()) : null,
        ];
    }

    private function touches(array $values, array $keys): bool
    {
        return array_intersect(array_keys($values), $keys) !== [];
    }

    private function loadNames(Collection $logs): void
    {
        $departmentIds = collect([$this->asset->department_id]);
        $subDepartmentIds = collect([$this->asset->sub_department_id]);

        foreach ($logs as $log) {
            foreach ([$log->old_values ?? [], $log->new_values ?? []] as $values) {
                $departmentIds->push($values['department_id'] ?? null);
                $subDepartmentIds->push($values['sub_department_id'] ?? null);
            }
        }

        // Moves can cross centres or reference since-deactivated records, so
        // look names up without the centre scope.
        $this->departmentNames = Department::withoutGlobalScopes()
            ->whereIn('id', $departmentIds->filter()->unique())
            ->pluck('name', 'id')
            ->all();
        $this->subDepartmentNames = SubDepartment::withoutGlobalScopes()
            ->whereIn('id', $subDepartmentIds->filter()->unique())
            ->pluck('name', 'id')
            ->all();
    }

    private function location($department, $subDepartment): string
    {
        $name = $this->departmentName($department);
        $sub = $this->subDepartmentName($subDepartment);

        return $sub !== '—' ? "{$name} / {$sub}" : $name;
    }

    private function departmentName($id): string
    {
        return $id ? ($this->departmentNames[$id] ?? "Department #{$id}") : '—';
    }

    private function subDepartmentName($id): string
    {
        return $id ? ($this->subDepartmentNames[$id] ?? "Sub Department #{$id}") : '—';
    }
}
