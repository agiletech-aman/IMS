<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'vendor_type',
        'category',
        'contact_person',
        'phone',
        'email',
        'website',
        'address',
        'amc_status',
        'contract_start',
        'contract_end',
        'preferred',
        'rating',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'contract_end' => 'date',
            'preferred' => 'boolean',
            'rating' => 'decimal:1',
        ];
    }
}
