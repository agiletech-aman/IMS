<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use CentreScoped;

   protected $fillable = [
    'asset_tag',
    'name',
    'asset_type_id',
    'brand_id',
    'department_id',
    'sub_department_id',
    'serial_number',
    'fr_number',
    'installation_date',
    'assigned_to',
    'status',
    'warranty_expiry',
    'amc_expiry',
    'image_path',
    'notes',

    'cpu',
    'hdd',
    'ram',
    'operating_system',
];

protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'warranty_expiry' => 'date', 'amc_expiry' => 'date',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subDepartment(): BelongsTo
    {
        return $this->belongsTo(SubDepartment::class);
    }
}
