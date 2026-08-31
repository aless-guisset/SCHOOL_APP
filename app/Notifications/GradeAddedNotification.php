<?php

namespace App\Notifications;

use App\Models\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeAddedNotification extends Notification
{
    // Pas de ShouldQueue : aucun worker de queue en prod — envoi synchrone, cf. TimesheetAssignedNotification.
    use Queueable;

    public function __construct(private readonly Grade $grade) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nouvelle note',
            'body' => $this->context()['message'],
            'url' => '/grades',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[School App] Nouvelle note')
            ->greeting('Bonjour,')
            ->line($this->context()['message'])
            ->action('Voir les notes', url('/grades'))
            ->line('Ceci est une notification automatique.');
    }

    /**
     * Données dérivées des relations, partagées par toArray() et toMail() pour
     * que les deux canaux ne puissent pas diverger.
     *
     * @return array{studentName: string, subjectName: string, message: string}
     */
    private function context(): array
    {
        $student = $this->grade->sectionUser?->userschoolrole?->user;
        $studentName = $student ? "{$student->firstname} {$student->lastname}" : 'votre enfant';
        $subjectName = $this->grade->subject?->name ?? 'une matière';

        return [
            'studentName' => $studentName,
            'subjectName' => $subjectName,
            'message' => "{$studentName} a reçu une nouvelle note en {$subjectName} : {$this->grade->grade}/{$this->grade->max_grade} ({$this->grade->period}).",
        ];
    }
}
