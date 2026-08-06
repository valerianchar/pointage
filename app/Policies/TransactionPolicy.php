<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function update(User $user, Transaction $transaction): bool
    {
        return $transaction->account->isAccessibleBy($user);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $transaction->account->isAccessibleBy($user);
    }
}
