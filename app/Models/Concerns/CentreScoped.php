<?php

namespace App\Models\Concerns;

use App\Services\CentreContextService;
use Illuminate\Database\Eloquent\Builder;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait CentreScoped
{
    protected static function bootCentreScoped(): void
    {
        static::addGlobalScope('centre', function (Builder $builder): void {
            $centre = app(CentreContextService::class)->selected();

            if ($centre !== null) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('centre'),
                    $centre,
                );
            }
        });

        static::creating(function ($model): void {
            $centre = app(CentreContextService::class)->selected();

            if ($centre !== null && blank($model->getAttribute('centre'))) {
                $model->setAttribute('centre', $centre);
            }
        });
    }

}