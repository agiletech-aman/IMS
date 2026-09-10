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
            if (filled($model->getAttribute('centre'))) {
                return;
            }

            // Seeders/factories/artisan commands run without a browser session
            // and have no centre to select — only real web requests are held
            // to "you must pick a centre first".
            if (app()->runningInConsole()) {
                return;
            }

            $model->setAttribute('centre', app(CentreContextService::class)->requireSelected());
        });
    }

}