<?php

namespace Database\Factories;

use App\Enums\BugReportStatus;
use App\Models\BugReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BugReport>
 */
class BugReportFactory extends Factory
{
    private static int $reportNumber = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => 'Signalement '.(++self::$reportNumber),
            'description' => 'Une description du bug rencontré.',
            'status' => BugReportStatus::Sent,
        ];
    }

    public function resolved(): static
    {
        return $this->state(['status' => BugReportStatus::Resolved]);
    }

    public function about(string $subject): static
    {
        return $this->state(['subject' => $subject]);
    }
}
