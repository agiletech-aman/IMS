<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    public const DEFAULTS = [
        'amc_expiry' => ['AMC expiry alert', 30],
        'warranty_expiry' => ['Warranty expiry alert', 30],
        'user_created' => ['New user alert', null],
        'user_deleted' => ['Deleted user alert', null],
        'asset_assigned' => ['Asset assignment alert', null],
        'report_generated' => ['Report generation alert', null],
        'vendor_changed' => ['Vendor / OEM alert', null],
        'complaint_changed' => ['Complaint workflow alert', null],
        'backup_completed' => ['Backup completed alert', null],
        'backup_failed' => ['Backup failure alert', null],
        'backup_health' => ['Backup health alert', null],
    ];

    protected $fillable = [
        'event_type',
        'label',
        'in_app_enabled',
        'email_enabled',
        'days_before',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'days_before' => 'integer',
        ];
    }

    public static function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $eventType => [$label, $daysBefore]) {
            self::firstOrCreate(['event_type' => $eventType], [
                'label' => $label,
                'in_app_enabled' => true,
                'email_enabled' => true,
                'days_before' => $daysBefore,
            ]);
        }
    }
}
