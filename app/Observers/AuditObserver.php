<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function created(Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $this->audit->record(
            'CREATE',
            $this->audit->moduleFor($model),
            $this->audit->labelFor($model).' was created.',
            $model,
            [],
            $model->getAttributes(),
        );
    }

    public function updated(Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $changes = collect($model->getChanges())->except('updated_at')->all();
        if ($changes === []) {
            return;
        }

        $this->audit->record(
            'UPDATE',
            $this->audit->moduleFor($model),
            $this->audit->labelFor($model).' was updated.',
            $model,
            collect($model->getOriginal())->only(array_keys($changes))->all(),
            $changes,
        );
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $this->audit->record(
            'DELETE',
            $this->audit->moduleFor($model),
            $this->audit->labelFor($model).' was deleted.',
            $model,
            $model->getAttributes(),
        );
    }
}
