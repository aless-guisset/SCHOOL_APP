<?php

namespace App\Notifications;

use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TimesheetAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Timesheet $timesheet,
        private readonly User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $date = Carbon::parse($this->timesheet->date)->format('d/m/Y');

        return [
            'title' => 'Nouvelle feuille de temps assignée',
            'body'  => "{$this->assignedBy->firstname} {$this->assignedBy->lastname} vous a assigné un créneau le {$date}.",
            'url'   => "/timesheets/{$this->timesheet->id}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = Carbon::parse($this->timesheet->date)->format('d/m/Y');

        return (new MailMessage)
            ->subject('[School App] Nouvelle feuille de temps assignée')
            ->greeting('Bonjour,')
            ->line("{$this->assignedBy->firstname} {$this->assignedBy->lastname} vous a assigné un nouveau créneau le {$date}.")
            ->action('Voir la feuille de temps', url("/timesheets/{$this->timesheet->id}"))
            ->line('Merci de vérifier votre planning.');
    }
}
