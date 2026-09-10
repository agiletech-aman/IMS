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

            $context = app(CentreContextService::class);
            $centre = $context->selected();

            if ($centre !== null && ! $context->isAll()) {
                $model->setAttribute('centre', $centre);

                return;
            }

            // Models that may legitimately span every centre (e.g. a
            // whole-system backup taken with no centre filter) skip the
            // "pick a centre first" requirement and let the column default
            // apply instead of erroring out.
            if (static::centreOptional()) {
                return;
            }

            $model->setAttribute('centre', $context->requireSelected());
        });
    }

    protected static function centreOptional(): bool
    {
        return false;
    }
}