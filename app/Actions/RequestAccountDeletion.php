<?php

namespace App\Actions;

use App\Events\AccountDeletionRequested;
use App\Models\Account;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestAccountDeletion
{
    public function __construct(private readonly DeleteAccount $deleteAccount) {}

    /**
     * Supprimer un compte partagé n'est pas un geste unilatéral : la demande
     * est ouverte, le demandeur donne d'office son accord, et les autres
     * votants sont prévenus — websocket tout de suite, bannière sur leur
     * accueil sinon. Sans autre votant, le compte est supprimé sur-le-champ.
     *
     * @return AccountDeletionRequest|null null quand la suppression a été immédiate
     */
    public function handle(Account $account, User $requester): ?AccountDeletionRequest
    {
        $otherVoterIds = $account->members()->accepted()
            ->where('user_id', '!=', $requester->id)
            ->pluck('user_id')
            ->when($account->user_id !== $requester->id, fn ($ids) => $ids->push($account->user_id))
            ->unique()
            ->values();

        if ($otherVoterIds->isEmpty()) {
            $this->deleteAccount->handle($account);

            return null;
        }

        $request = DB::transaction(function () use ($account, $requester): AccountDeletionRequest {
            $request = $account->deletionRequest()->firstOrCreate(['requested_by' => $requester->id]);
            $request->approvals()->firstOrCreate(['user_id' => $requester->id]);

            return $request;
        });

        AccountDeletionRequested::dispatch($request, $otherVoterIds->all());

        return $request;
    }
}
