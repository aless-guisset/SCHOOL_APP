<?php

namespace App\Notifications;

use App\Models\MedicalCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MedicalCertificateSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly MedicalCertificate $certificate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $studentName = $this->certificate->sectionUser?->userschoolrole?->user
            ? "{$this->certificate->sectionUser->userschoolrole->user->firstname} {$this->certificate->sectionUser->userschoolrole->user->lastname}"
            : 'un élève';

        return [
            'title' => "Nouveau certificat médical à valider",
            'body'  => "{$studentName} a soumis un certificat médical pour validation.",
            'url'   => '/medical-certificates',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = $this->certificate->sectionUser?->userschoolrole?->user
            ? "{$this->certificate->sectionUser->userschoolrole->user->firstname} {$this->certificate->sectionUser->userschoolrole->user->lastname}"
            : 'Un élève';

        return (new MailMessage)
            ->subject('[School App] Nouveau certificat médical à valider')
            ->greeting('Bonjour,')
            ->line("{$studentName} a soumis un certificat médical, en attente de votre validation.")
            ->action('Voir les certificats en attente', url('/medical-certificates'));
    }
}
