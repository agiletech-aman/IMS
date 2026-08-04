<?php

namespace App\Http\Controllers;

use App\Models\BackupSchedule;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackupScheduleController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['enabled'] = $request->boolean('enabled');
        $data['created_by'] = $request->session()->get('static_auth_user.name', 'Administrator');
        $schedule = new BackupSchedule($data);
        $schedule->next_run_at = $schedule->calculateNextRun();
        $schedule->save();
        $audit->record('CREATE', 'Backup Schedules', "{$schedule->name} schedule created.", $schedule);

        return back()->with('success', 'Automatic backup schedule created.');
    }

    public function update(Request $request, BackupSchedule $schedule, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['enabled'] = $request->boolean('enabled');
        $schedule->fill($data);
        $schedule->next_run_at = $schedule->enabled ? $schedule->calculateNextRun() : null;
        $schedule->save();
        $audit->record('UPDATE', 'Backup Schedules', "{$schedule->name} schedule updated.", $schedule);

        return back()->with('success', 'Backup schedule updated.');
    }

    public function destroy(
        Request $request,
        BackupSchedule $schedule,
        AuditLogger $audit,
    ): RedirectResponse {
        $request->validate(['confirmation' => ['required', Rule::in([(string) $schedule->id])]]);
        $name = $schedule->name;
        $audit->record('DELETE', 'Backup Schedules', "{$name} schedule deleted.", $schedule);
        $schedule->delete();

        return back()->with('success', 'Backup schedule deleted. Existing backups were retained.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'backup_type' => ['required', Rule::in(['database', 'files', 'full'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'run_at' => ['required', 'date_format:H:i'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6', 'required_if:frequency,weekly'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31', 'required_if:frequency,monthly'],
            'retention_type' => ['required', Rule::in(['count', 'days'])],
            'retention_value' => ['required', 'integer', 'between:1,3650'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        if ($data['frequency'] !== 'weekly') {
            $data['day_of_week'] = null;
        }
        if ($data['frequency'] !== 'monthly') {
            $data['day_of_month'] = null;
        }

        return $data;
    }
}
