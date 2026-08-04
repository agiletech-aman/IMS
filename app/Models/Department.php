<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'head_name', 'description', 'status'];

    public function subDepartments(): HasMany
    {
        return $this->hasMany(SubDepartment::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
