<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends Model
{
    use CentreScoped;

    protected $fillable = ['name', 'code', 'description', 'status'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
