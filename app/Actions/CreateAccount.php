<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateAccount
{
    /**
     * Déclare un compte et crée d'emblée les tags par défaut de son type —
     * et, pour un compte de marché, les positions déclarées à la volée.
     *
     * @param  list<array{asset_id: string, quantity: string}>  $positions
     */
    public function handle(User $user, string $name, AccountType $type, int $initialBalanceCents, array $positions = []): Account
    {
        return DB::transaction(function () use ($user, $name, $type, $initialBalanceCents, $positions): Account {
            $account = $user->accounts()->create([
                'name' => $name,
                'type' => $type,
                'initial_balance_cents' => $initialBalanceCents,
            ]);

            $account->tags()->createMany(
                array_map(fn (string $tagName): array => ['name' => $tagName], $type->defaultTags())
            );

            $account->positions()->createMany(
                array_map(fn (array $position): array => [
                    'provider' => $type->assetProvider(),
                    'asset_id' => $position['asset_id'],
                    'label' => $position['asset_id'],
                    'quantity' => $position['quantity'],
                ], $positions)
            );

            return $account;
        });
    }
}
