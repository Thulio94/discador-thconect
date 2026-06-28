<?php

namespace App\Observers;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;

final class AuditableModelObserver
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function created(Model $model): void
    {
        $this->audit->recordModelChange($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->audit->recordModelChange($model, 'updated');
    }
}
