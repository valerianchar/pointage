<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Credit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credit>
 */
class CreditFactory extends Factory
{
    private static int $creditNumber = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => 'Crédit '.(++self::$creditNumber),
            'borrowed_cents' => 1_000_000,
            'remaining_cents' => 500_000,
            'monthly_cents' => 25_000,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    public function of(int $borrowedCents, int $remainingCents, int $monthlyCents): static
    {
        return $this->state([
            'borrowed_cents' => $borrowedCents,
            'remaining_cents' => $remainingCents,
            'monthly_cents' => $monthlyCents,
        ]);
    }
}
