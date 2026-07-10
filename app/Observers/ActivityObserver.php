<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $changes = [
            'before' => $model->getOriginal(),
            'after'  => $model->getDirty(),
        ];

        $this->log('updated', $model, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    private function log(string $event, Model $model, ?array $changes = null): void
    {
        $user = Auth::user();

        ActivityLog::create([
            'event'       => $event,
            'model_type'  => get_class($model),
            'model_id'    => $model->getKey(),
            'model_label' => method_exists($model, 'getActivityLabel') ? $model->getActivityLabel() : ($model->name ?? null),
            'user_id'     => $user?->id,
            'user_email'  => $user?->email,
            'changes'     => $changes,
            'ip_address'  => Request::ip(),
        ]);
    }
}
