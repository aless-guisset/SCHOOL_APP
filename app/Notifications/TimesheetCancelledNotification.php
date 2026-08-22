<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TimesheetCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $date,
        private readonly User $cancelledBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $date = Carbon::parse($this->date)->format('d/m/Y');

        return [
            'title' => 'Créneau annulé',
            'body'  => "{$this->cancelledBy->firstname} {$this->cancelledBy->lastname} a annulé votre créneau du {$date}.",
            'url'   => '/timesheets',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = Carbon::parse($this->date)->format('d/m/Y');

        return (new MailMessage)
            ->subject('[School App] Créneau annulé')
            ->greeting('Bonjour,')
            ->line("{$this->cancelledBy->firstname} {$this->cancelledBy->lastname} a annulé votre créneau du {$date}.")
            ->action('Voir mon planning', url('/timesheets'))
            ->line('Merci de vérifier votre planning mis à jour.');
    }
}
