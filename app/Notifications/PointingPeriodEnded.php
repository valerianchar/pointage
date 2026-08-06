<?php

namespace App\Notifications;

use App\Models\Account;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Rappel envoyé au navigateur le dernier jour de la période de pointage d'un
 * compte : il reste des opérations à rapprocher du relevé. Cliquer ouvre le
 * pointage guidé du compte.
 */
class PointingPeriodEnded extends Notification
{
    public function __construct(
        private readonly Account $account,
        private readonly int $pendingCount,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $operations = $this->pendingCount > 1
            ? $this->pendingCount.' opérations à pointer'
            : 'une opération à pointer';

        return (new WebPushMessage)
            ->title('Fin de période — '.$this->account->name)
            ->body('Votre relevé est arrivé ? Il reste '.$operations.'.')
            ->data(['url' => route('pointing.session', $this->account, absolute: false)]);
    }
}
