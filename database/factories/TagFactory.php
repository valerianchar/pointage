<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    private static int $tagNumber = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => 'Tag '.(++self::$tagNumber),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
