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
     * et, selon le type, les positions du compte de marché ou les invitations
     * du compte joint.
     *
     * @param  list<array{asset_id: string, quantity: string}>  $positions
     * @param  list<int>  $memberIds  Utilisateurs invités, en attente d'acceptation
     */
    public function handle(User $user, string $name, AccountType $type, int $initialBalanceCents, array $positions = [], array $memberIds = []): Account
    {
        return DB::transaction(function () use ($user, $name, $type, $initialBalanceCents, $positions, $memberIds): Account {
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

            $account->members()->createMany(
                array_map(fn (int $memberId): array => [
                    'user_id' => $memberId,
                    'invited_by' => $user->id,
                ], $memberIds)
            );

            return $account;
        });
    }
}
