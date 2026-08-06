<?php

namespace App\Notifications;

use App\Models\Account;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Invitation à rejoindre un compte joint, envoyée au navigateur de l'invité
 * s'il est abonné aux notifications. Cliquer ouvre l'accueil, où la bannière
 * d'invitation attend sa réponse.
 */
class JointAccountInvitation extends Notification
{
    public function __construct(
        private readonly Account $account,
        private readonly User $inviter,
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
        return (new WebPushMessage)
            ->title('Invitation — '.$this->account->name)
            ->body($this->inviter->name.' vous invite à rejoindre ce compte joint.')
            ->data(['url' => route('dashboard', absolute: false)]);
    }
}
