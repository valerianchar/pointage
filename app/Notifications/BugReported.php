<?php

namespace App\Notifications;

use App\Models\BugReport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Signalement de bug transmis au mainteneur de l'instance — l'adresse vient de
 * la configuration, pas d'un utilisateur : la notification part « à la demande »
 * (Notification::route), sans notifiable en base.
 */
class BugReported extends Notification
{
    public function __construct(private readonly BugReport $bugReport) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Pointage] '.$this->bugReport->subject)
            ->greeting('Nouveau signalement de bug')
            ->line('De : '.$this->bugReport->user->name.' ('.$this->bugReport->user->email.')')
            ->line('Sujet : '.$this->bugReport->subject)
            ->line($this->bugReport->description)
            ->salutation('— Pointage');
    }
}
