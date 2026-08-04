<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    private static int $accountNumber = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Compte '.(++self::$accountNumber),
            'type' => AccountType::Current,
            'initial_balance_cents' => 0,
        ];
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function startingAt(int $cents): static
    {
        return $this->state(['initial_balance_cents' => $cents]);
    }
}
