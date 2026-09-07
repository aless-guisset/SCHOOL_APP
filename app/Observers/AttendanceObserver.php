<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Notifications\AbsenceRecordedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        // Retard ('R') n'est volontairement pas une absence — seul 'A'
        // déclenche AbsenceRecordedNotification, comme avant l'introduction
        // du statut Retard (l'ancien `is_present=false` ne pouvait signifier
        // qu'une absence, jamais un retard qui n'existait pas encore).
        if ($attendance->presence_status !== 'A') {
            return;
        }

        $this->notify($attendance);
    }

    public function updated(Attendance $attendance): void
    {
        if (! $attendance->wasChanged('presence_status') || $attendance->presence_status !== 'A') {
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

        // Un échec de livraison (Resend indisponible, quota, expéditeur mal
        // configuré…) ne doit jamais transformer une prise de présence
        // réussie en erreur 500 : on journalise et on continue.
        try {
            Notification::send($parents, new AbsenceRecordedNotification($attendance));
        } catch (\Throwable $e) {
            Log::warning('Notification parent (absence) échouée', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
