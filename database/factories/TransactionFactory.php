<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    private static int $transactionNumber = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'label' => 'Opération '.(++self::$transactionNumber),
            'amount_cents' => -1000,
            'occurred_on' => now()->startOfMonth(),
            'pointed_at' => null,
        ];
    }

    public function expense(int $cents): static
    {
        return $this->state(['amount_cents' => -abs($cents)]);
    }

    public function income(int $cents): static
    {
        return $this->state(['amount_cents' => abs($cents)]);
    }

    public function pointed(): static
    {
        return $this->state(['pointed_at' => now()]);
    }

    public function on(string $date): static
    {
        return $this->state(['occurred_on' => $date]);
    }
}
