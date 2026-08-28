<?php

namespace App\Notifications;

use App\Models\School;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly School $school,
        private readonly User   $requester,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Nouvelle demande d'accès : {$this->requester->firstname} {$this->requester->lastname}",
            'body'  => "{$this->requester->firstname} {$this->requester->lastname} demande l'accès à {$this->school->name}.",
            'url'   => '/access-requests',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[School App] Nouvelle demande d'accès — {$this->school->name}")
            ->greeting('Bonjour,')
            ->line("{$this->requester->firstname} {$this->requester->lastname} ({$this->requester->email}) demande l'accès à {$this->school->name}.")
            ->action('Voir les demandes en attente', url('/access-requests'));
    }
}
