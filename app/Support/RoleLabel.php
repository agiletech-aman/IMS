<?php

namespace App\Support;

class RoleLabel
{
    private const DISPLAY_MAP = [
        'Administrator' => 'Super Admin',
        'Sub admin' => 'Admin',
    ];

    public static function display(?string $role): string
    {
        return self::DISPLAY_MAP[$role] ?? (string) $role;
    }
}
