<?php

namespace App\Policies;

use App\Models\Credit;
use App\Models\User;

class CreditPolicy
{
    public function delete(User $user, Credit $credit): bool
    {
        return $credit->account->isAccessibleBy($user);
    }
}
