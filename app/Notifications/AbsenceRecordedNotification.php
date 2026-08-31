<?php

namespace App\Notifications;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbsenceRecordedNotification extends Notification
{
    // Pas de ShouldQueue : aucun worker de queue en prod — envoi synchrone, cf. TimesheetAssignedNotification.
    use Queueable;

    public function __construct(private readonly Attendance $attendance) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Absence enregistrée',
            'body' => $this->context()['message'],
            'url' => '/dashboard',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[School App] Absence enregistrée')
            ->greeting('Bonjour,')
            ->line($this->context()['message'])
            ->line('Ceci est une notification automatique.');
    }

    /**
     * Données dérivées des relations, partagées par toArray() et toMail() pour
     * que les deux canaux ne puissent pas diverger.
     *
     * @return array{studentName: string, subjectName: string, date: ?string, message: string}
     */
    private function context(): array
    {
        $student = $this->attendance->sectionUser?->userschoolrole?->user;
        $studentName = $student ? "{$student->firstname} {$student->lastname}" : 'votre enfant';

        // Carbon::parse(null) renvoie la date du jour : sans ce garde, une
        // feuille de temps manquante produirait un email affirmant que
        // l'absence a eu lieu aujourd'hui. On dégrade en libellé neutre,
        // comme $subjectName.
        $rawDate = $this->attendance->timesheet?->date;
        $date = $rawDate ? Carbon::parse($rawDate)->format('d/m/Y') : null;
        $dateFragment = $date ? "le {$date}" : 'à une date non précisée';
        $subjectName = $this->attendance->timesheet?->subject?->name ?? 'un cours';

        return [
            'studentName' => $studentName,
            'subjectName' => $subjectName,
            'date' => $date,
            'message' => "{$studentName} a été marqué(e) absent(e) en {$subjectName} {$dateFragment}.",
        ];
    }
}
