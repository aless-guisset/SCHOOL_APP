<?php

namespace App\Notifications;

use App\Models\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeAddedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Grade $grade) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $student = $this->grade->sectionUser?->userschoolrole?->user;
        $studentName = $student ? "{$student->firstname} {$student->lastname}" : 'votre enfant';
        $subjectName = $this->grade->subject?->name ?? 'une matière';

        return [
            'title' => 'Nouvelle note',
            'body'  => "{$studentName} a reçu une nouvelle note en {$subjectName} : {$this->grade->grade}/{$this->grade->max_grade} ({$this->grade->period}).",
            'url'   => '/grades',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $student = $this->grade->sectionUser?->userschoolrole?->user;
        $studentName = $student ? "{$student->firstname} {$student->lastname}" : 'votre enfant';
        $subjectName = $this->grade->subject?->name ?? 'une matière';

        return (new MailMessage)
            ->subject('[School App] Nouvelle note')
            ->greeting('Bonjour,')
            ->line("{$studentName} a reçu une nouvelle note en {$subjectName} : {$this->grade->grade}/{$this->grade->max_grade} ({$this->grade->period}).")
            ->action('Voir les notes', url('/grades'))
            ->line('Ceci est une notification automatique.');
    }
}
