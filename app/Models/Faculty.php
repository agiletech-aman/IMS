<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use CentreScoped;

    protected $fillable = [
        'unique_id', 'name', 'email', 'contact', 'address', 'image_path',
        'status', 'department_id', 'fb_type', 'room_number', 'remark', 'centre',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'assigned_to', 'name');
    }
}