<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    private static int $templateNumber = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'label' => 'Récurrente '.(++self::$templateNumber),
            'amount_cents' => -1000,
            'day_of_month' => 1,
            'is_active' => true,
        ];
    }

    public function onDay(int $dayOfMonth): static
    {
        return $this->state(['day_of_month' => $dayOfMonth]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
