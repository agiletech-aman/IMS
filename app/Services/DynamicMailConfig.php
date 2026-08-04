<?php

namespace App\Services;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Mail;

class DynamicMailConfig
{
    public function apply(SmtpSetting $setting): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $setting->encryption === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.host' => $setting->host,
            'mail.mailers.smtp.port' => $setting->port,
            'mail.mailers.smtp.username' => $setting->username,
            'mail.mailers.smtp.password' => $setting->password,
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name,
        ]);

        Mail::purge('smtp');
    }
}
