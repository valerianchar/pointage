<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountClosing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountClosing>
 */
class AccountClosingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $month = CarbonImmutable::now()->subMonthNoOverflow();

        return [
            'account_id' => Account::factory(),
            'period_start' => $month->startOfMonth()->toDateString(),
            'period_end' => $month->endOfMonth()->toDateString(),
            'theoretical_balance_cents' => 100_000,
            'real_balance_cents' => 100_000,
            'pointed_expenses_cents' => 50_000,
            'pointed_incomes_cents' => 150_000,
            'note' => null,
        ];
    }

    public function balancing(int $theoreticalCents, int $realCents): static
    {
        return $this->state([
            'theoretical_balance_cents' => $theoreticalCents,
            'real_balance_cents' => $realCents,
        ]);
    }

    public function noted(string $note): static
    {
        return $this->state(['note' => $note]);
    }
}
