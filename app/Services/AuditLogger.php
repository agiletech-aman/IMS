<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    private static bool $recording = false;

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'email_error',
    ];

    public function record(
        string $action,
        string $module,
        string $description,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        string $result = 'Success',
        array $metadata = [],
    ): ?AuditLog {
        if (self::$recording || ! Schema::hasTable('audit_logs')) {
            return null;
        }

        self::$recording = true;

        try {
            $request = app()->bound('request') ? request() : null;
            $actor = $request?->hasSession()
                ? $request->session()->get('static_auth_user', [])
                : [];

            if (($actor['role'] ?? null) === 'Administrator') {
                return null;
            }

            return AuditLog::create([
                'centre' => $actor['centre'] ?? $this->selectedCentre($request),
                'actor_name' => $actor['name'] ?? 'System',
                'actor_email' => $actor['email'] ?? null,
                'actor_role' => $actor['role'] ?? null,
                'action' => Str::upper($action),
                'module' => $module,
                'description' => Str::limit($description, 255, ''),
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'old_values' => $this->sanitize($oldValues) ?: null,
                'new_values' => $this->sanitize($newValues) ?: null,
                'metadata' => $this->sanitize($metadata) ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'route_name' => $request?->route()?->getName(),
                'request_method' => $request?->method(),
                'result' => $result,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        } finally {
            self::$recording = false;
        }
    }

    public function moduleFor(Model $model): string
    {
        return match (class_basename($model)) {
            'Admin' => 'Administrators',
            'Asset' => 'Assets',
            'AssetCategory' => 'Categories',
            'AssetType' => 'Types',
            'Brand' => 'Brands',
            'Complaint' => 'Complaint Management',
            'Department' => 'Departments',
            'SubDepartment' => 'Sub Departments',
            'User' => 'Users',
            'Vendor' => 'Vendors',
            'SystemNotification' => 'Alerts',
            'NotificationPreference' => 'Notification Settings',
            'SmtpSetting' => 'SMTP Settings',
            'SystemSetting' => 'Settings',
            default => Str::headline(Str::pluralStudly(class_basename($model))),
        };
    }

    public function labelFor(Model $model): string
    {
        foreach (['complaint_number', 'asset_tag', 'unique_id', 'code', 'title', 'name', 'email', 'event_type'] as $field) {
            if (filled($model->getAttribute($field))) {
                return (string) $model->getAttribute($field);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function sanitize(array $values): array
    {
        return collect($values)
            ->reject(fn ($value, $key) => in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true))
            ->map(function ($value) {
                if (is_array($value)) {
                    return $this->sanitize($value);
                }

                if (is_object($value) && method_exists($value, 'toISOString')) {
                    return $value->toISOString();
                }

                return $value;
            })
            ->all();
    }

    private function selectedCentre(?\Illuminate\Http\Request $request): string
    {
        $centre = $request?->hasSession()
            ? $request->session()->get('selected_centre', 'lucknow')
            : 'lucknow';

        return in_array($centre, ['noida', 'lucknow'], true) ? $centre : 'lucknow';
    }
}
