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
    'subtype_values',
    'asset_subtype_id',
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
];

protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'warranty_expiry' => 'date', 'amc_expiry' => 'date',
            'subtype_values' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function subtype(): BelongsTo
    {
        return $this->belongsTo(AssetSubtype::class, 'asset_subtype_id');
    }

    /**
     * The selected Subtype's configured parameter values, ordered by the
     * parent Type's current parameter list, for read-only display on the asset.
     *
     * @return array<int, array{label: string, value: ?string}>
     */
    public function subtypeParameterValues(): array
    {
        if (! $this->subtype) {
            return [];
        }

        $values = $this->subtype->parameter_values ?? [];
        $labels = $this->type?->parameters ?: array_keys($values);

        return collect($labels)
            ->map(fn ($param) => ['label' => $param, 'value' => $values[$param] ?? null])
            ->all();
    }

    /**
     * Resolve this asset's stored subtype field values (keyed by AssetSubtype id)
     * into ['label' => subtype name, 'value' => stored value] pairs for display.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function subtypeFieldValues(): array
    {
        $values = $this->subtype_values ?? [];

        if ($values === []) {
            return [];
        }

        $labels = AssetSubtype::whereIn('id', array_keys($values))->pluck('name', 'id');

        return collect($values)
            ->map(fn ($value, $subtypeId) => [
                'label' => $labels[$subtypeId] ?? "Field #{$subtypeId}",
                'value' => $value,
            ])
            ->values()
            ->all();
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
