<?php

namespace App\Queries;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class TagSpending
{
    private const UNTAGGED_LABEL = '—';

    /**
     * Dépenses du mois d'un compte, regroupées par tag, du plus gros poste au plus petit.
     *
     * @return list<array{tag: string, amount_cents: int}>
     */
    public function forAccountMonth(Account $account, CarbonInterface $month): array
    {
        return $this->groupedByTag($month)
            ->where('transactions.account_id', $account->id)
            ->get()
            ->map($this->toSpendingRow())
            ->all();
    }

    /**
     * Même regroupement, tous comptes confondus, limité aux plus gros postes.
     *
     * @return list<array{tag: string, amount_cents: int}>
     */
    public function forUserMonth(User $user, CarbonInterface $month, int $limit = 5): array
    {
        return $this->groupedByTag($month)
            ->whereIn('transactions.account_id', $user->accounts()->select('accounts.id'))
            ->limit($limit)
            ->get()
            ->map($this->toSpendingRow())
            ->all();
    }

    /**
     * @return Builder<Transaction>
     */
    private function groupedByTag(CarbonInterface $month): Builder
    {
        return Transaction::query()
            ->leftJoin('tags', 'tags.id', '=', 'transactions.tag_id')
            ->where('transactions.amount_cents', '<', 0)
            // Une baisse de marché n'est pas une dépense à répartir par tag.
            ->where('transactions.is_revaluation', false)
            ->whereBetween('transactions.occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->groupBy('tags.name')
            ->selectRaw('tags.name as tag_name, SUM(-transactions.amount_cents) as spent_cents')
            ->orderByDesc('spent_cents');
    }

    private function toSpendingRow(): callable
    {
        return fn ($row): array => [
            'tag' => $row->tag_name ?? self::UNTAGGED_LABEL,
            'amount_cents' => (int) $row->spent_cents,
        ];
    }
}
