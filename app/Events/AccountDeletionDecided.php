<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La demande de suppression est tranchée — unanimité (supprimé) ou refus
 * (le compte reste) : tous les membres en sont avertis sur-le-champ.
 */
class AccountDeletionDecided implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public readonly string $accountName,
        public readonly bool $deleted,
        public readonly array $recipientIds,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $userId) => new PrivateChannel('users.'.$userId), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return 'account.deletion-decided';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'account_name' => $this->accountName,
            'deleted' => $this->deleted,
        ];
    }
}
