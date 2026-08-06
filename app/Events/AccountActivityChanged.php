<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une opération d'un compte partagé vient de bouger — ajout, correction,
 * pointage, récurrente de la nuit, réévaluation : les autres membres
 * rafraîchissent leur écran, liste et solde global compris.
 */
class AccountActivityChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public readonly int $accountId,
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
        return 'account.activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['account_id' => $this->accountId];
    }
}
