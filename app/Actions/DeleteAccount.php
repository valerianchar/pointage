<?php

namespace App\Actions;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

final class DeleteAccount
{
    /**
     * Supprime un compte et tout ce qu'il porte — opérations, modèles
     * récurrents, tags, crédits, clôtures.
     *
     * La suppression est explicite, enfant par enfant, dans une transaction :
     * les clés étrangères ne cascadent pas, pour qu'aucune suppression de
     * masse ne puisse arriver par accident depuis un autre chemin.
     */
    public function handle(Account $account): void
    {
        DB::transaction(function () use ($account): void {
            $account->closings()->delete();
            $account->credits()->delete();
            $account->positions()->delete();
            $account->transactions()->delete();
            $account->recurringTransactions()->delete();
            $account->tags()->delete();
            $account->delete();
        });
    }
}
