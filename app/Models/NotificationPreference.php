<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use App\Services\CentreContextService;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use CentreScoped;

    public const DEFAULTS = [
        'amc_expiry' => ['AMC expiry alert', 30],
        'warranty_expiry' => ['Warranty expiry alert', 30],
        'user_created' => ['New user alert', null],
        'user_deleted' => ['Deleted user alert', null],
        'access_account_created' => ['New access account alert', null],
        'access_account_deleted' => ['Deleted access account alert', null],
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
        'centre',
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
        $centre = app(CentreContextService::class)->selected() ?? 'lucknow';

        foreach (self::DEFAULTS as $eventType => [$label, $daysBefore]) {
            self::createOrFirst(['event_type' => $eventType, 'centre' => $centre], [
                'label' => $label,
                'in_app_enabled' => true,
                'email_enabled' => true,
                'days_before' => $daysBefore,
            ]);
        }
    }
}
