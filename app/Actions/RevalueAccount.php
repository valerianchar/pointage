<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;

final class RevalueAccount
{
    /**
     * Recale le solde du compte sur sa valeur réelle — celle que le courtier ou
     * la plateforme affiche. La différence devient une opération de
     * réévaluation, née pointée : elle ne vient pas du relevé, elle le suit.
     *
     * @return Transaction|null L'opération créée, ou null si le solde était déjà juste
     */
    public function handle(Account $account, int $targetBalanceCents, CarbonInterface $today): ?Transaction
    {
        $differenceCents = $targetBalanceCents - $account->balance_cents;

        if ($differenceCents === 0) {
            return null;
        }

        return $account->transactions()->create([
            'label' => 'Réévaluation marché',
            'amount_cents' => $differenceCents,
            'occurred_on' => $today->toDateString(),
            'pointed_at' => $today,
            'is_revaluation' => true,
        ]);
    }
}
