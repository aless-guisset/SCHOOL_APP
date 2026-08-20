<?php

namespace App\Notifications;

use App\Models\School;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly School $school,
        private readonly User   $submittedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Nouvelle demande d'école : {$this->school->name}",
            'body'  => "{$this->submittedBy->firstname} {$this->submittedBy->lastname} a soumis une demande de création d'école.",
            'url'   => '/schools/pending',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[School App] Nouvelle demande d'école : {$this->school->name}")
            ->greeting('Bonjour,')
            ->line("{$this->submittedBy->firstname} {$this->submittedBy->lastname} ({$this->submittedBy->email}) a soumis une demande de création d'école.")
            ->line("**École :** {$this->school->name}")
            ->action('Voir les demandes en attente', url('/schools/pending'))
            ->line('Merci de traiter cette demande depuis le panneau administrateur.');
    }
}
