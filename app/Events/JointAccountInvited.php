<?php

namespace App\Events;

use App\Models\Account;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une invitation à rejoindre un compte joint vient de partir : si l'invité a
 * l'app ouverte, la bannière apparaît sur son accueil sans qu'il recharge —
 * le web push couvre le cas de l'app fermée.
 */
class JointAccountInvited implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly Account $account,
        public readonly User $inviter,
        public readonly int $inviteeId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->inviteeId)];
    }

    public function broadcastAs(): string
    {
        return 'account.invited';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'account_name' => $this->account->name,
            'inviter_name' => $this->inviter->name,
        ];
    }
}
