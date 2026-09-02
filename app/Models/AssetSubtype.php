<?php

namespace App\Models;

use App\Models\Concerns\CentreScoped;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetSubtype extends Model
{
    use CentreScoped;

    protected $fillable = ['asset_type_id', 'name', 'code', 'description', 'status', 'is_required'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * Assets store their subtype field values as JSON (keyed by subtype id),
     * so this isn't a normal FK relation — count matching assets via a JSON lookup.
     */
    protected function assetsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => Asset::whereRaw(
                'JSON_CONTAINS_PATH(subtype_values, "one", ?)',
                ['$."'.$this->id.'"']
            )->count(),
        );
    }
}
