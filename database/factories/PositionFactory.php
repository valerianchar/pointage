<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'asset_id' => 'bitcoin',
            'label' => 'bitcoin',
            'quantity' => '0.05',
        ];
    }

    public function of(string $assetId, string $quantity): static
    {
        return $this->state(['asset_id' => $assetId, 'label' => $assetId, 'quantity' => $quantity]);
    }
}
