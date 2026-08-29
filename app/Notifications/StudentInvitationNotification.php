<?php

namespace App\Notifications;

use App\Models\StudentInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly StudentInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $student = $this->invitation->student->user;

        return (new MailMessage)
            ->subject("[School App] Invitation à suivre le parcours scolaire de {$student->firstname}")
            ->greeting('Bonjour,')
            ->line("{$student->firstname} {$student->lastname} vous invite à suivre son parcours scolaire (notes, horaire, présences) sur School App.")
            ->action('Accepter l\'invitation', url("/invitations/student/{$this->invitation->token}/accept"))
            ->line('Ce lien est valable 7 jours et ne peut être utilisé qu\'une seule fois.');
    }
}
