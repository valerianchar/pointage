<?php

namespace App\Queries;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class RecurringInstances
{
    /**
     * Instances du mois issues des modèles récurrents.
     *
     * @return Collection<int, Transaction>
     */
    public function forUserMonth(User $user, CarbonInterface $month): Collection
    {
        return Transaction::query()
            ->whereIn('account_id', $user->accounts()->select('accounts.id'))
            ->whereNotNull('recurring_transaction_id')
            ->whereBetween('occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->with(['account', 'tag'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Modèles actifs dont l'instance du mois n'existe pas encore — leur jour
     * n'est pas arrivé. L'écran les montre « à venir » plutôt que de les taire.
     *
     * @return Collection<int, RecurringTransaction>
     */
    public function upcomingForUserMonth(User $user, CarbonInterface $month): Collection
    {
        return RecurringTransaction::query()
            ->whereIn('account_id', $user->accounts()->select('accounts.id'))
            ->where('is_active', true)
            ->whereDoesntHave('transactions', fn ($query) => $query->whereBetween('occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]))
            ->with(['account', 'tag'])
            ->orderBy('day_of_month')
            ->orderBy('id')
            ->get();
    }
}
