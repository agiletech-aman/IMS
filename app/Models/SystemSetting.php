<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use CentreScoped;

    protected $fillable = [
        'application_name',
        'language',
        'timezone',
        'date_format',
        'currency',
        'company_name',
        'tax_number',
        'registered_address',
        'support_email',
        'support_phone',
        'session_timeout',
        'password_expiry_days',
        'require_mfa',
        'strong_password',
        'restrict_concurrent_sessions',
        'default_theme',
    ];

    protected function casts(): array
    {
        return [
            'session_timeout' => 'integer',
            'password_expiry_days' => 'integer',
            'require_mfa' => 'boolean',
            'strong_password' => 'boolean',
            'restrict_concurrent_sessions' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'application_name' => 'Agile Tech Solutions IIM',
            'language' => 'en',
            'timezone' => config('app.timezone', 'Asia/Kolkata'),
            'date_format' => 'd M Y',
            'currency' => 'INR',
            'company_name' => 'Agile Tech Solutions',
            'support_email' => 'support@agiletech.net.in',
            'session_timeout' => 30,
            'password_expiry_days' => 90,
            'strong_password' => true,
            'default_theme' => 'light',
        ]);
    }
}
