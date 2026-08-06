<?php

namespace App\Queries;

use App\Enums\AccountType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

final class MarketTypeTotals
{
    /**
     * Valeur totale des comptes d'un type de marché — crypto, PEA — et
     * variation du jour, lue dans les réévaluations datées d'aujourd'hui.
     *
     * @return array{value_cents: int, account_count: int, day_change_cents: int}|null null quand aucun compte de ce type
     */
    public function forUser(User $user, AccountType $type, CarbonImmutable $today): ?array
    {
        $accounts = $user->accounts()
            ->where('type', $type)
            ->withSum('transactions', 'amount_cents')
            ->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        $dayChangeCents = Transaction::query()
            ->whereIn('account_id', $accounts->pluck('id'))
            ->where('is_revaluation', true)
            ->where('occurred_on', $today->toDateString())
            ->sum('amount_cents');

        return [
            'value_cents' => (int) $accounts->sum(fn ($account): int => $account->balance_cents),
            'account_count' => $accounts->count(),
            'day_change_cents' => (int) $dayChangeCents,
        ];
    }
}
