<?php

namespace App\Events;

use App\Models\AccountDeletionRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un membre demande la suppression du compte joint : les autres votants le
 * savent tout de suite — la bannière sur leur accueil fait foi si l'app est
 * fermée à ce moment-là.
 *
 * Diffusé sans file d'attente : la pile n'a pas de worker dédié, et l'instant
 * est tout l'intérêt.
 */
class AccountDeletionRequested implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public readonly AccountDeletionRequest $request,
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
        return 'account.deletion-requested';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'account_name' => $this->request->account->name,
            'requester_name' => $this->request->requester->name,
        ];
    }
}
