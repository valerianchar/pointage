<?php

namespace App\Actions;

use App\Events\AccountDeletionDecided;
use App\Models\AccountDeletionRequest;
use App\Models\User;

final class SettleAccountDeletion
{
    public function __construct(private readonly DeleteAccount $deleteAccount) {}

    /**
     * Enregistre l'accord d'un votant ; à l'unanimité, le compte disparaît et
     * tous les membres en sont avertis.
     *
     * @return bool true si le compte a été supprimé
     */
    public function approve(AccountDeletionRequest $request, User $voter): bool
    {
        $request->approvals()->firstOrCreate(['user_id' => $voter->id]);

        if (! $request->isUnanimous()) {
            return false;
        }

        $voterIds = $request->voters()->pluck('id')->all();
        $accountName = $request->account->name;

        $this->deleteAccount->handle($request->account);

        AccountDeletionDecided::dispatch($accountName, true, $voterIds);

        return true;
    }

    /**
     * Un seul refus — ou le renoncement du demandeur — clôt la demande :
     * le compte reste, tout le monde le sait.
     */
    public function refuse(AccountDeletionRequest $request): void
    {
        $voterIds = $request->voters()->pluck('id')->all();
        $accountName = $request->account->name;

        $request->approvals()->delete();
        $request->delete();

        AccountDeletionDecided::dispatch($accountName, false, $voterIds);
    }
}
