<?php

namespace App\Queries;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class UserAccounts
{
    /**
     * Comptes de l'utilisateur avec leur solde courant et leur reste à pointer.
     *
     * @return Collection<int, Account>
     */
    public function forSidebar(User $user): Collection
    {
        return $this->withBalanceAndPending($user)->get();
    }

    /**
     * Même liste, tags inclus : l'écran d'ajout change de compte sans aller-retour serveur.
     *
     * @return Collection<int, Account>
     */
    public function withTags(User $user): Collection
    {
        return $this->withBalanceAndPending($user)
            ->with(['tags' => fn ($query) => $query->orderBy('id')])
            ->get();
    }

    /**
     * @return Builder<Account>
     */
    private function withBalanceAndPending(User $user): Builder
    {
        return $user->accessibleAccounts()
            // Solde et reste à pointer sont agrégés en base : une requête, pas une par compte.
            ->withSum('transactions', 'amount_cents')
            // Une opération à venir n'est pas encore à pointer : elle entrera
            // dans le cycle le jour où elle tombe.
            ->withCount(['transactions as pending_count' => fn ($query) => $query
                ->whereNull('pointed_at')
                ->where('occurred_on', '<=', now()->toDateString())])
            ->orderBy('id');
    }
}
