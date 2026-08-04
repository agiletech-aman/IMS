<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = [
        'asset_tag', 'name', 'asset_category_id', 'asset_type_id', 'brand_id',
        'department_id', 'sub_department_id', 'model', 'serial_number', 'purchase_date',
        'installation_date', 'location', 'assigned_to', 'status', 'warranty_expiry',
        'amc_expiry', 'image_path', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'installation_date' => 'date',
            'warranty_expiry' => 'date', 'amc_expiry' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
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
