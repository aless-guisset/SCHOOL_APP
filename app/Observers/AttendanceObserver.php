<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Notifications\AbsenceRecordedNotification;
use Illuminate\Support\Facades\Notification;

class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        if ($attendance->is_present) {
            return;
        }

        $this->notify($attendance);
    }

    public function updated(Attendance $attendance): void
    {
        if (! $attendance->wasChanged('is_present') || $attendance->is_present) {
            return;
        }

        $this->notify($attendance);
    }

    private function notify(Attendance $attendance): void
    {
        $studentUsr = $attendance->sectionUser?->userschoolrole;

        if (! $studentUsr) {
            return;
        }

        $parents = $studentUsr->activeParentUsers();

        if ($parents->isEmpty()) {
            return;
        }

        Notification::send($parents, new AbsenceRecordedNotification($attendance));
    }
}
