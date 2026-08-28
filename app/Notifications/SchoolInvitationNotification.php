<?php

namespace App\Notifications;

use App\Models\SchoolInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly SchoolInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->invitation->school;
        $role = $this->invitation->role;

        return (new MailMessage)
            ->subject("[School App] Invitation à rejoindre {$school->name}")
            ->greeting('Bonjour,')
            ->line("Vous avez été invité(e) à rejoindre {$school->name} en tant que {$role->name}.")
            ->action('Accepter l\'invitation', url("/invitations/{$this->invitation->token}/accept"))
            ->line('Ce lien est valable 7 jours et ne peut être utilisé qu\'une seule fois.');
    }
}
