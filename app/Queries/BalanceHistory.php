<?php

namespace App\Queries;

use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;

final class BalanceHistory
{
    /**
     * Solde du patrimoine à la fin de chacune des dernières semaines.
     *
     * Le solde courant est connu ; les soldes passés s'en déduisent en retirant
     * les opérations survenues depuis. Les totaux sont agrégés par jour en base,
     * puis regroupés par semaine ici — le regroupement hebdomadaire n'a pas la
     * même syntaxe d'un moteur à l'autre.
     *
     * @return list<array{label: string, balance_cents: int}>
     */
    public function weeklyForUser(User $user, CarbonInterface $reference, int $weeks = 8): array
    {
        $firstWeekStart = $reference->copy()->startOfWeek()->subWeeks($weeks - 1);
        $accountIds = $user->accessibleAccounts()->select('accounts.id');

        $currentBalanceCents = (int) $user->accessibleAccounts()->sum('initial_balance_cents')
            + (int) Transaction::query()->whereIn('account_id', $accountIds)->sum('amount_cents');

        $dailyTotals = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('occurred_on', '>=', $firstWeekStart->toDateString())
            ->groupBy('occurred_on')
            ->selectRaw('occurred_on, SUM(amount_cents) as daily_total')
            ->pluck('daily_total', 'occurred_on');

        $points = [];

        for ($weekOffset = 0; $weekOffset < $weeks; $weekOffset++) {
            $weekEnd = $firstWeekStart->copy()->addWeeks($weekOffset)->endOfWeek();

            $afterWeekEndCents = (int) $dailyTotals
                ->filter(fn (int|string $total, string $day): bool => $day > $weekEnd->toDateString())
                ->sum();

            $points[] = [
                'label' => 'Semaine du '.$weekEnd->copy()->startOfWeek()->translatedFormat('j M'),
                'balance_cents' => $currentBalanceCents - $afterWeekEndCents,
            ];
        }

        return $points;
    }
}
