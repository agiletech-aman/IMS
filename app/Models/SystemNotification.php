<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = [
        'event_type',
        'title',
        'message',
        'severity',
        'module',
        'data',
        'unique_key',
        'in_app_visible',
        'read_at',
        'emailed_at',
        'email_error',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'in_app_visible' => 'boolean',
            'read_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }
}
