<?php

namespace App\Observers;

use App\Models\Grade;
use App\Notifications\GradeAddedNotification;
use Illuminate\Support\Facades\Notification;

class GradeObserver
{
    public function created(Grade $grade): void
    {
        $studentUsr = $grade->sectionUser?->userschoolrole;

        if (! $studentUsr) {
            return;
        }

        $parents = $studentUsr->activeParentUsers();

        if ($parents->isEmpty()) {
            return;
        }

        Notification::send($parents, new GradeAddedNotification($grade));
    }
}
