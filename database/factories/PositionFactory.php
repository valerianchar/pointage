<?php

namespace Database\Factories;

use App\Enums\AssetProvider;
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
            'provider' => AssetProvider::Coingecko,
            'asset_id' => 'bitcoin',
            'label' => 'bitcoin',
            'quantity' => '0.05',
        ];
    }

    public function of(string $assetId, string $quantity): static
    {
        return $this->state(['asset_id' => $assetId, 'label' => $assetId, 'quantity' => $quantity]);
    }

    public function etf(string $ticker, string $quantity): static
    {
        return $this->state([
            'provider' => AssetProvider::Yahoo,
            'asset_id' => $ticker,
            'label' => $ticker,
            'quantity' => $quantity,
        ]);
    }
}
