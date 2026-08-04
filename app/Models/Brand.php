<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'code', 'country', 'support_contact', 'logo_path', 'status'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
