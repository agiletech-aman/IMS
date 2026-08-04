<?php

namespace App\Services;

use App\Mail\SystemAlertMail;
use App\Models\NotificationPreference;
use App\Models\SmtpSetting;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public function __construct(private readonly DynamicMailConfig $mailConfig)
    {
    }

    public function send(
        string $eventType,
        string $title,
        string $message,
        string $severity,
        string $module,
        array $data = [],
        ?string $uniqueKey = null,
    ): SystemNotification {
        NotificationPreference::seedDefaults();
        $preference = NotificationPreference::where('event_type', $eventType)->firstOrFail();

        if ($uniqueKey && ($existing = SystemNotification::where('unique_key', $uniqueKey)->first())) {
            return $existing;
        }

        $notification = SystemNotification::create([
            'event_type' => $eventType,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'module' => $module,
            'data' => $data,
            'unique_key' => $uniqueKey,
            'in_app_visible' => $preference->in_app_enabled,
        ]);

        if (! $preference->email_enabled) {
            return $notification;
        }

        $smtp = SmtpSetting::current();
        $recipients = $smtp->recipients();
        if (! $smtp->enabled || ! $smtp->host || $recipients === []) {
            return $notification;
        }

        try {
            $this->mailConfig->apply($smtp);
            Mail::to($recipients)->send(new SystemAlertMail($title, $message, $severity, $module));
            $notification->update(['emailed_at' => now(), 'email_error' => null]);
        } catch (Throwable $exception) {
            report($exception);
            $notification->update(['email_error' => mb_strimwidth($exception->getMessage(), 0, 2000)]);
        }

        return $notification;
    }
}
