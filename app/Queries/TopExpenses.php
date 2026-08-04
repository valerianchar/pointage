<?php

namespace App\Queries;

use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;

final class TopExpenses
{
    /**
     * Plus grosses dépenses du mois, tous comptes confondus.
     *
     * @return list<array{label: string, account_name: string, tag: string|null, amount_cents: int}>
     */
    public function forUserMonth(User $user, CarbonInterface $month, int $limit = 3): array
    {
        return Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('tags', 'tags.id', '=', 'transactions.tag_id')
            ->where('accounts.user_id', $user->id)
            ->where('transactions.amount_cents', '<', 0)
            ->whereBetween('transactions.occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            // La dépense la plus lourde est le montant le plus négatif.
            ->orderBy('transactions.amount_cents')
            ->limit($limit)
            ->get([
                'transactions.label',
                'transactions.amount_cents',
                'accounts.name as account_name',
                'tags.name as tag_name',
            ])
            ->map(fn ($row): array => [
                'label' => $row->label,
                'account_name' => $row->account_name,
                'tag' => $row->tag_name,
                'amount_cents' => (int) $row->amount_cents,
            ])
            ->all();
    }
}
