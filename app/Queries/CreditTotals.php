<?php

namespace App\Queries;

use App\Models\Credit;
use App\Models\User;

final class CreditTotals
{
    /**
     * Capital restant dû et charge mensuelle, tous crédits confondus.
     *
     * @return array{remaining_cents: int, monthly_cents: int, count: int}
     */
    public function forUser(User $user): array
    {
        $totals = Credit::query()
            ->whereIn('account_id', $user->accessibleAccounts()->select('accounts.id'))
            ->selectRaw('COALESCE(SUM(remaining_cents), 0) as remaining_cents')
            ->selectRaw('COALESCE(SUM(monthly_cents), 0) as monthly_cents')
            ->selectRaw('COUNT(*) as credit_count')
            ->first();

        return [
            'remaining_cents' => (int) $totals->remaining_cents,
            'monthly_cents' => (int) $totals->monthly_cents,
            'count' => (int) $totals->credit_count,
        ];
    }
}
