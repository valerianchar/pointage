<?php

namespace App\Queries;

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
}
