<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operation_can_be_pointed_then_unpointed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($account)->create();

        $this->actingAs($user)
            ->patch("/operations/{$transaction->id}/pointage")
            ->assertRedirect();

        $this->assertNotNull($transaction->fresh()->pointed_at);

        $this->patch("/operations/{$transaction->id}/pointage");

        $this->assertNull($transaction->fresh()->pointed_at);
    }

    public function test_pointing_another_profile_operation_is_refused(): void
    {
        $foreignAccount = Account::factory()->for(User::factory()->create())->create();
        $transaction = Transaction::factory()->for($foreignAccount)->create();

        $this->actingAs(User::factory()->create())
            ->patch("/operations/{$transaction->id}/pointage")
            ->assertForbidden();

        $this->assertNull($transaction->fresh()->pointed_at);
    }

    public function test_pointing_does_not_change_the_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->startingAt(100_000)->create();
        $transaction = Transaction::factory()->for($account)->expense(5_000)->create();

        $this->actingAs($user)->patch("/operations/{$transaction->id}/pointage");

        $this->assertSame(95_000, $account->fresh()->balance_cents);
    }
}
