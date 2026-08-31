<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbsenceRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Attendance $attendance) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $student = $this->attendance->sectionUser?->userschoolrole?->user;
        $studentName = $student ? "{$student->firstname} {$student->lastname}" : 'votre enfant';
        $date = \Carbon\Carbon::parse($this->attendance->timesheet?->date)->format('d/m/Y');
        $subjectName = $this->attendance->timesheet?->subject?->name ?? 'un cours';

        return [
            'title' => 'Absence enregistrée',
            'body'  => "{$studentName} a été marqué(e) absent(e) en {$subjectName} le {$date}.",
            'url'   => '/dashboard',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $student = $this->attendance->sectionUser?->userschoolrole?->user;
        $studentName = $student ? "{$student->firstname} {$student->lastname}" : 'votre enfant';
        $date = \Carbon\Carbon::parse($this->attendance->timesheet?->date)->format('d/m/Y');
        $subjectName = $this->attendance->timesheet?->subject?->name ?? 'un cours';

        return (new MailMessage)
            ->subject('[School App] Absence enregistrée')
            ->greeting('Bonjour,')
            ->line("{$studentName} a été marqué(e) absent(e) en {$subjectName} le {$date}.")
            ->line('Ceci est une notification automatique.');
    }
}
