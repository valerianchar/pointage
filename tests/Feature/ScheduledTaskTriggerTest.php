<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledTaskTriggerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-suffisamment-long-pour-etre-realiste';

    public function test_the_trigger_does_not_exist_until_a_token_is_configured(): void
    {
        config(['pointage.tasks_token' => null]);

        $this->postJson('/taches/recurrentes')->assertNotFound();
    }

    public function test_the_trigger_is_rate_limited(): void
    {
        config(['pointage.tasks_token' => self::TOKEN]);

        foreach (range(1, 6) as $attempt) {
            $this->withToken('jeton-devine-'.$attempt)->postJson('/taches/recurrentes')->assertForbidden();
        }

        $this->withToken(self::TOKEN)->postJson('/taches/recurrentes')->assertTooManyRequests();
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        config(['pointage.tasks_token' => self::TOKEN]);
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->create();

        $this->postJson('/taches/recurrentes')->assertForbidden();
        $this->withToken('mauvais-jeton')->postJson('/taches/recurrentes')->assertForbidden();

        $this->assertSame(0, $account->transactions()->count());
    }

    public function test_the_right_token_generates_the_month_and_can_be_replayed(): void
    {
        config(['pointage.tasks_token' => self::TOKEN]);
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->create();

        $this->withToken(self::TOKEN)
            ->postJson('/taches/recurrentes')
            ->assertOk()
            ->assertExactJson(['created' => 1]);

        // Rejouable sans créer de doublon : un cron qui insiste ne fait aucun dégât.
        $this->withToken(self::TOKEN)
            ->postJson('/taches/recurrentes')
            ->assertOk()
            ->assertExactJson(['created' => 0]);

        $this->assertSame(1, $account->transactions()->count());
        $this->assertNull($account->transactions()->sole()->pointed_at);
    }
}
