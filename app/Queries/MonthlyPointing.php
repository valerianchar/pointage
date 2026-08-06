<?php

namespace App\Queries;

use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;

final class MonthlyPointing
{
    /**
     * Opérations du mois et part restant à pointer.
     *
     * @return array{pending_count: int, total_count: int}
     */
    public function forUserMonth(User $user, CarbonInterface $month): array
    {
        $counts = Transaction::query()
            ->whereIn('account_id', $user->accessibleAccounts()->select('accounts.id'))
            ->whereBetween('occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->selectRaw('COUNT(*) as total_count')
            // Une opération à venir n'est pas encore à pointer : elle attend son jour.
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pointed_at IS NULL AND occurred_on <= ? THEN 1 ELSE 0 END), 0) as pending_count',
                [$month->toDateString()],
            )
            ->first();

        return [
            'pending_count' => (int) $counts->pending_count,
            'total_count' => (int) $counts->total_count,
        ];
    }
}
