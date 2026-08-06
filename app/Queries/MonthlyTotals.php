<?php

namespace App\Queries;

use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;

final class MonthlyTotals
{
    /**
     * Ajouts et dépenses du mois, tous comptes confondus, en centimes positifs.
     *
     * @return array{income_cents: int, expense_cents: int}
     */
    public function forUser(User $user, CarbonInterface $month): array
    {
        $totals = Transaction::query()
            ->whereIn('account_id', $user->accessibleAccounts()->select('accounts.id'))
            // Une réévaluation de marché n'est ni un ajout ni une dépense.
            ->where('is_revaluation', false)
            ->whereBetween('occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents ELSE 0 END), 0) as income_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_cents < 0 THEN -amount_cents ELSE 0 END), 0) as expense_cents')
            ->first();

        return [
            'income_cents' => (int) $totals->income_cents,
            'expense_cents' => (int) $totals->expense_cents,
        ];
    }
}
