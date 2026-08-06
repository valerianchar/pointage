<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClosingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_closing_panel_receives_the_requested_account_and_its_operations(): void
    {
        $this->travelTo('2026-08-06');
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['name' => 'Premier']);
        $account = Account::factory()->for($user)->create(['name' => 'Second']);

        Transaction::factory()->for($account)->create(['label' => 'Dans la période', 'occurred_on' => '2026-08-03', 'pointed_at' => now()]);
        Transaction::factory()->for($account)->create(['label' => 'Vieille non pointée', 'occurred_on' => '2026-06-10', 'pointed_at' => null]);

        $this->actingAs($user)
            ->get('/bilan?cloture='.$account->id.'&pointer=1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('closing_account_id', $account->id)
                ->where('closing_open', true)
                ->has('closing_transactions', 2)
                ->where('closing_transactions.0.label', 'Vieille non pointée')
                ->where('closing_transactions.1.label', 'Dans la période'));
    }

    public function test_someone_else_s_account_cannot_feed_the_closing_panel(): void
    {
        $stranger = Account::factory()->create(['name' => 'Étranger']);
        Transaction::factory()->for($stranger)->create(['pointed_at' => null]);

        $user = User::factory()->create();
        Account::factory()->for($user)->create();

        // Le compte demandé n'appartient pas au visiteur : repli sur son premier compte.
        $this->actingAs($user)
            ->get('/bilan?cloture='.$stranger->id.'&pointer=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('closing_transactions', 0));
    }

    public function test_a_forgotten_expense_added_from_the_closing_is_born_pointed(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($account->user)
            ->from('/bilan?cloture='.$account->id.'&pointer=1')
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => 'depense',
                'amount' => '8,20',
                'label' => 'Péage oublié',
                'pointed' => true,
                'stay' => true,
            ])
            ->assertRedirect('/bilan?cloture='.$account->id.'&pointer=1');

        $transaction = $account->transactions()->sole();
        $this->assertTrue($transaction->isPointed());
        $this->assertSame('Péage oublié', $transaction->label);
    }

    public function test_closing_shifts_the_next_period_to_the_day_after_the_end(): void
    {
        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 15]);

        $this->actingAs($account->user)
            ->post('/clotures', ['account_id' => $account->id, 'real_balance' => '100'])
            ->assertRedirect();

        // Du 1 au 15 clôturé : la période suivante court du 16 au 15.
        $this->assertSame(16, $account->fresh()->period_start_day);
        $this->assertSame(15, $account->fresh()->period_end_day);
    }

    public function test_a_period_ending_on_the_thirty_first_rolls_back_to_the_first(): void
    {
        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);

        $this->actingAs($account->user)
            ->post('/clotures', ['account_id' => $account->id, 'real_balance' => '100'])
            ->assertRedirect();

        $this->assertSame(1, $account->fresh()->period_start_day);
    }

    public function test_shared_accounts_carry_their_period_deadline(): void
    {
        $this->travelTo('2026-08-06');
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['period_start_day' => 1, 'period_end_day' => 10]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('accounts.0.days_until_period_end', 4));
    }

    public function test_push_subscriptions_can_be_stored_and_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/notifications/abonnement', [
                'endpoint' => 'https://push.example/abc',
                'keys' => ['p256dh' => 'clef-p256dh', 'auth' => 'clef-auth'],
            ])
            ->assertRedirect();

        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)
            ->delete('/notifications/abonnement', ['endpoint' => 'https://push.example/abc'])
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->pushSubscriptions()->count());
    }
}
