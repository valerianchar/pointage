<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateAccount
{
    /**
     * Déclare un compte et crée d'emblée les tags par défaut de son type.
     */
    public function handle(User $user, string $name, AccountType $type, int $initialBalanceCents): Account
    {
        return DB::transaction(function () use ($user, $name, $type, $initialBalanceCents): Account {
            $account = $user->accounts()->create([
                'name' => $name,
                'type' => $type,
                'initial_balance_cents' => $initialBalanceCents,
            ]);

            $account->tags()->createMany(
                array_map(fn (string $tagName): array => ['name' => $tagName], $type->defaultTags())
            );

            return $account;
        });
    }
}
