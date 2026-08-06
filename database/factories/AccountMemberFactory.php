<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountMember>
 */
class AccountMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'invited_by' => null,
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()]);
    }
}
