<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;
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
            'school_id'   => $this->resolveSchoolId($model),
            'model_label' => method_exists($model, 'getActivityLabel') ? $model->getActivityLabel() : ($model->name ?? null),
            'user_id'     => $user?->id,
            'user_email'  => $user?->email,
            'changes'     => $changes,
            'ip_address'  => Request::ip(),
        ]);
    }

    /**
     * Résout l'école propriétaire du modèle observé, pour scoper le widget dashboard par école.
     * User/Role n'ont pas de portée école unique (un User peut appartenir à N écoles) — exclus
     * volontairement (null), ils restent visibles uniquement via /logs (admin plateforme).
     */
    private function resolveSchoolId(Model $model): ?int
    {
        return match (true) {
            $model instanceof Course, $model instanceof Section, $model instanceof Classroom,
            $model instanceof Lesson, $model instanceof Resource
                => $model->school_id,
            $model instanceof Subject        => $model->course?->school_id,
            $model instanceof Timesheet      => $model->userSchoolRole?->school_id,
            $model instanceof Schedule       => $model->sectionCourse?->course?->school_id,
            $model instanceof UserSchoolRole => $model->school_id,
            $model instanceof School         => $model->id,
            default => null,
        };
    }
}
