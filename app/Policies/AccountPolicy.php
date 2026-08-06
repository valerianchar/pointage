<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $account->isAccessibleBy($user);
    }

    public function update(User $user, Account $account): bool
    {
        return $account->isAccessibleBy($user);
    }

    /**
     * Supprimer le compte ou gérer ses membres reste au propriétaire : un
     * membre partage les opérations, pas les clés.
     */
    public function delete(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function manageMembers(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }
}
