<?php

namespace App\Queries;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;

final class TagSpending
{
    private const UNTAGGED_LABEL = '—';

    /**
     * Dépenses du mois regroupées par tag, du plus gros poste au plus petit.
     *
     * @return list<array{tag: string, amount_cents: int}>
     */
    public function forAccountMonth(Account $account, CarbonInterface $month): array
    {
        return Transaction::query()
            ->leftJoin('tags', 'tags.id', '=', 'transactions.tag_id')
            ->where('transactions.account_id', $account->id)
            ->where('transactions.amount_cents', '<', 0)
            ->whereBetween('transactions.occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->groupBy('tags.name')
            ->selectRaw('tags.name as tag_name, SUM(-transactions.amount_cents) as spent_cents')
            ->orderByDesc('spent_cents')
            ->get()
            ->map(fn ($row): array => [
                'tag' => $row->tag_name ?? self::UNTAGGED_LABEL,
                'amount_cents' => (int) $row->spent_cents,
            ])
            ->all();
    }
}
