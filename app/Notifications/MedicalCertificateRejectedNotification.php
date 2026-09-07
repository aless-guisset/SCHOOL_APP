<?php

namespace App\Notifications;

use App\Models\MedicalCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MedicalCertificateRejectedNotification extends Notification
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
        return [
            'title' => 'Certificat médical refusé',
            'body'  => 'Votre certificat médical soumis n\'a pas été validé.'
                .($this->certificate->rejection_reason ? " Motif : {$this->certificate->rejection_reason}" : ''),
            'url'   => '/medical-certificates',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[School App] Certificat médical refusé')
            ->greeting('Bonjour,')
            ->line('Votre certificat médical soumis n\'a pas été validé.');

        if ($this->certificate->rejection_reason) {
            $mail->line("Motif : {$this->certificate->rejection_reason}");
        }

        return $mail->action('Voir mes certificats', url('/medical-certificates'));
    }
}
