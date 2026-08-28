<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessRequestDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly School $school,
        private readonly bool   $approved,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->approved ? 'Demande acceptée' : 'Demande refusée',
            'body'  => $this->approved
                ? "Votre demande d'accès à {$this->school->name} a été acceptée."
                : "Votre demande d'accès à {$this->school->name} a été refusée.",
            'url'   => '/dashboard',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->greeting('Bonjour,');

        return $this->approved
            ? $message->subject('[School App] Votre demande a été acceptée')
                ->line("Votre demande d'accès à {$this->school->name} a été acceptée.")
                ->action('Se connecter', url('/dashboard'))
            : $message->subject('[School App] Votre demande a été refusée')
                ->line("Votre demande d'accès à {$this->school->name} a été refusée.");
    }
}
