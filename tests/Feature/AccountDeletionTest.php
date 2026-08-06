<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Credit;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_account_takes_its_whole_history_with_it(): void
    {
        $account = Account::factory()->create();
        $tag = Tag::factory()->for($account)->create();
        $template = $account->recurringTransactions()->create([
            'label' => 'Loyer',
            'amount_cents' => -82_000,
            'day_of_month' => 2,
        ]);
        Transaction::factory()->for($account)->create(['tag_id' => $tag->id, 'recurring_transaction_id' => $template->id]);
        Credit::factory()->for($account)->create();
        $account->closings()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'theoretical_balance_cents' => 100_000,
            'real_balance_cents' => 100_000,
            'pointed_expenses_cents' => 0,
            'pointed_incomes_cents' => 0,
        ]);

        $this->actingAs($account->user)
            ->delete("/compte/{$account->id}")
            ->assertRedirect('/');

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Tag::count());
        $this->assertSame(0, Credit::count());
        $this->assertDatabaseCount('recurring_transactions', 0);
        $this->assertDatabaseCount('account_closings', 0);
    }

    public function test_other_accounts_survive_the_deletion(): void
    {
        $user = User::factory()->create();
        $doomed = Account::factory()->for($user)->create();
        $survivor = Account::factory()->for($user)->create();
        Transaction::factory()->for($survivor)->create();

        $this->actingAs($user)->delete("/compte/{$doomed->id}")->assertRedirect('/');

        $this->assertNotNull($survivor->fresh());
        $this->assertSame(1, $survivor->transactions()->count());
    }

    public function test_someone_else_s_account_cannot_be_deleted(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->for($account)->create();

        $this->actingAs(User::factory()->create())
            ->delete("/compte/{$account->id}")
            ->assertForbidden();

        $this->assertNotNull($account->fresh());
        $this->assertSame(1, $account->transactions()->count());
    }
}
