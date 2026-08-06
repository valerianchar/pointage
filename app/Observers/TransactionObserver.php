<?php

namespace App\Observers;

use App\Events\AccountActivityChanged;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

/**
 * Toute écriture d'opération sur un compte partagé — saisie, correction,
 * pointage, récurrente de la nuit, réévaluation — avertit les autres membres
 * en websocket : leur écran recharge liste et solde global sans un geste.
 */
class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->notifyOtherMembers($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->notifyOtherMembers($transaction);
    }

    public function deleted(Transaction $transaction): void
    {
        $this->notifyOtherMembers($transaction);
    }

    private function notifyOtherMembers(Transaction $transaction): void
    {
        $account = $transaction->account;

        // Un compte sans membre accepté n'a personne d'autre à prévenir.
        if ($account === null || $account->members()->accepted()->doesntExist()) {
            return;
        }

        /*
         * Tous les membres sauf l'auteur du geste — la nuit, sans personne au
         * clavier, tout le monde est prévenu.
         */
        $recipientIds = $account->members()->accepted()->pluck('user_id')
            ->push($account->user_id)
            ->unique()
            ->reject(fn (int $userId): bool => $userId === Auth::id())
            ->values();

        if ($recipientIds->isNotEmpty()) {
            AccountActivityChanged::dispatch($account->id, $recipientIds->all());
        }
    }
}
