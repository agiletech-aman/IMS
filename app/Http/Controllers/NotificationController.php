<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\SmtpSetting;
use App\Models\SystemNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        NotificationPreference::seedDefaults();
        $query = SystemNotification::where('in_app_visible', true);

        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        return view('notifications.index', [
            'notifications' => $query->latest()->paginate(15)->withQueryString(),
            'preferences' => NotificationPreference::orderBy('id')->get(),
            'smtp' => SmtpSetting::current(),
            'stats' => [
                'unread' => SystemNotification::where('in_app_visible', true)->whereNull('read_at')->count(),
                'critical' => SystemNotification::where('in_app_visible', true)->where('severity', 'critical')->whereNull('read_at')->count(),
                'warnings' => SystemNotification::where('in_app_visible', true)->where('severity', 'warning')->whereNull('read_at')->count(),
                'read_today' => SystemNotification::where('in_app_visible', true)->whereDate('read_at', today())->count(),
            ],
            'sourceSummary' => SystemNotification::where('in_app_visible', true)
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('module, count(*) as total')
                ->groupBy('module')
                ->orderByDesc('total')
                ->limit(7)
                ->pluck('total', 'module'),
        ]);
    }

    public function markRead(SystemNotification $notification): RedirectResponse
    {
        $notification->update(['read_at' => $notification->read_at ?: now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(): RedirectResponse
    {
        $count = SystemNotification::where('in_app_visible', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $this->audit->record(
            'UPDATE',
            'Alerts',
            "{$count} notifications were marked as read.",
            metadata: ['notification_count' => $count],
        );

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(SystemNotification $notification): RedirectResponse
    {
        $notification->delete();

        return back()->with('success', 'Notification deleted.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        NotificationPreference::seedDefaults();
        $validated = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*.in_app' => ['nullable', Rule::in(['1'])],
            'preferences.*.email' => ['nullable', Rule::in(['1'])],
            'preferences.*.days_before' => ['nullable', 'integer', 'between:1,365'],
        ]);

        foreach (NotificationPreference::all() as $preference) {
            $values = $validated['preferences'][$preference->event_type] ?? [];
            $preference->update([
                'in_app_enabled' => isset($values['in_app']),
                'email_enabled' => isset($values['email']),
                'days_before' => $preference->days_before !== null
                    ? ($values['days_before'] ?? $preference->days_before)
                    : null,
            ]);
        }

        return back()->with('success', 'Notification delivery preferences updated.')->with('activeSettingsTab', 'notifications');
    }
}
