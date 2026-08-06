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
     * Chaque membre peut engager la suppression — mais sur un compte partagé,
     * elle n'aboutit qu'avec l'accord de tous les autres.
     */
    public function delete(User $user, Account $account): bool
    {
        return $account->isAccessibleBy($user);
    }

    public function manageMembers(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }
}
