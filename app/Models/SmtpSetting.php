<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    protected $fillable = [
        'enabled',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'notification_emails',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'enabled' => config('mail.default') === 'smtp',
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port', 587),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'notification_emails' => config('mail.from.address'),
        ]);
    }

    public function recipients(): array
    {
        return collect(preg_split('/[,;\s]+/', (string) $this->notification_emails))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
