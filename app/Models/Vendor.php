<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use CentreScoped;

    protected $fillable = [
        'centre',
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
