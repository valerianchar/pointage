<?php

namespace App\Queries;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class PointedActivity
{
    private const UNTAGGED_LABEL = '—';

    /**
     * Activité de la fenêtre de pointage courante, tous comptes confondus :
     * totaux pointés, reste à pointer, et dépenses pointées par tag.
     *
     * Chaque compte a sa propre fenêtre — « du 1 au 31 » ici, « du 5 au 4 »
     * là — donc les opérations sont filtrées compte par compte, en mémoire,
     * après une seule requête couvrant l'union des fenêtres.
     *
     * @return array{
     *     expenses_cents: int,
     *     incomes_cents: int,
     *     pointed_count: int,
     *     pending_count: int,
     *     by_tag: list<array{tag: string, amount_cents: int}>,
     * }
     */
    public function forUser(User $user, CarbonInterface $today): array
    {
        $accounts = $user->accessibleAccounts()->get();

        if ($accounts->isEmpty()) {
            return ['expenses_cents' => 0, 'incomes_cents' => 0, 'pointed_count' => 0, 'pending_count' => 0, 'by_tag' => []];
        }

        $periods = $accounts->mapWithKeys(fn (Account $account) => [$account->id => $account->pointingPeriod($today)]);

        $inWindow = Transaction::query()
            ->with('tag')
            ->whereIn('account_id', $accounts->modelKeys())
            ->whereBetween('occurred_on', [
                $periods->min(fn ($period) => $period->start)->toDateString(),
                $periods->max(fn ($period) => $period->end)->toDateString(),
            ])
            ->get()
            ->filter(fn (Transaction $transaction) => $periods[$transaction->account_id]->contains($transaction->occurred_on));

        $pointed = $inWindow->filter(fn (Transaction $transaction) => $transaction->isPointed());
        // Les réévaluations comptent dans le pointage, jamais dans les flux.
        $pointedFlows = $pointed->where('is_revaluation', false);

        return [
            'expenses_cents' => (int) (-1 * $pointedFlows->where('amount_cents', '<', 0)->sum('amount_cents')),
            'incomes_cents' => (int) $pointedFlows->where('amount_cents', '>', 0)->sum('amount_cents'),
            'pointed_count' => $pointed->count(),
            'pending_count' => $inWindow->count() - $pointed->count(),
            'by_tag' => $this->spendingByTag($pointedFlows),
        ];
    }

    /**
     * Totaux pointés d'un seul compte sur sa fenêtre courante — les chiffres
     * figés dans une clôture.
     *
     * @return array{expenses_cents: int, incomes_cents: int}
     */
    public function totalsForAccount(Account $account, CarbonInterface $today): array
    {
        $period = $account->pointingPeriod($today);

        $pointed = $account->transactions()
            ->whereNotNull('pointed_at')
            ->where('is_revaluation', false)
            ->whereBetween('occurred_on', [$period->start->toDateString(), $period->end->toDateString()])
            ->get();

        return [
            'expenses_cents' => (int) (-1 * $pointed->where('amount_cents', '<', 0)->sum('amount_cents')),
            'incomes_cents' => (int) $pointed->where('amount_cents', '>', 0)->sum('amount_cents'),
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $pointed
     * @return list<array{tag: string, amount_cents: int}>
     */
    private function spendingByTag(Collection $pointed): array
    {
        return $pointed
            ->where('amount_cents', '<', 0)
            ->groupBy(fn (Transaction $transaction) => $transaction->tag->name ?? self::UNTAGGED_LABEL)
            ->map(fn (Collection $transactions, string $tag) => [
                'tag' => $tag,
                'amount_cents' => (int) (-1 * $transactions->sum('amount_cents')),
            ])
            ->sortByDesc('amount_cents')
            ->values()
            ->all();
    }
}
