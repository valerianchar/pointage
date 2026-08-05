<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountClosing;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_shows_the_pointed_activity_of_the_current_period(): void
    {
        CarbonImmutable::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->create([
            'label' => 'Salaire',
            'amount_cents' => 240_000,
            'occurred_on' => '2026-08-01',
            'pointed_at' => '2026-08-02',
        ]);
        Transaction::factory()->for($account)->create([
            'label' => 'Loyer',
            'amount_cents' => -82_000,
            'occurred_on' => '2026-08-02',
            'pointed_at' => '2026-08-03',
        ]);
        Transaction::factory()->for($account)->create([
            'label' => 'Carrefour',
            'amount_cents' => -6_490,
            'occurred_on' => '2026-08-03',
            'pointed_at' => null,
        ]);
        // Hors fenêtre : ne compte ni dans les totaux ni dans le reste à pointer.
        Transaction::factory()->for($account)->create([
            'label' => 'Vieux loyer',
            'amount_cents' => -82_000,
            'occurred_on' => '2026-07-02',
            'pointed_at' => '2026-07-03',
        ]);

        $this->actingAs($user)
            ->get('/bilan')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Closings/Index')
                ->where('activity.expenses_cents', 82_000)
                ->where('activity.incomes_cents', 240_000)
                ->where('activity.pointed_count', 2)
                ->where('activity.pending_count', 1)
                ->where('month_label', 'Août 2026'));
    }

    public function test_the_activity_follows_each_account_own_period(): void
    {
        CarbonImmutable::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'period_start_day' => 5,
            'period_end_day' => 4,
        ]);

        // Le 3 août précède le début de fenêtre (du 5 août au 4 septembre).
        Transaction::factory()->for($account)->create([
            'amount_cents' => -10_000,
            'occurred_on' => '2026-08-03',
            'pointed_at' => '2026-08-04',
        ]);
        Transaction::factory()->for($account)->create([
            'amount_cents' => -25_000,
            'occurred_on' => '2026-08-10',
            'pointed_at' => '2026-08-11',
        ]);

        $this->actingAs($user)
            ->get('/bilan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activity.expenses_cents', 25_000)
                ->where('activity.pointed_count', 1));
    }

    public function test_closing_the_month_freezes_the_balances_and_the_variance(): void
    {
        CarbonImmutable::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->startingAt(100_000)->create();

        Transaction::factory()->for($account)->create([
            'amount_cents' => -20_000,
            'occurred_on' => '2026-08-05',
            'pointed_at' => '2026-08-06',
        ]);

        $this->actingAs($user)
            ->post('/clotures', [
                'account_id' => $account->id,
                'real_balance' => '78 760,00 €',
                'note' => '  Oubli : péage + espèces  ',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $closing = AccountClosing::sole();
        $this->assertSame('2026-08-01', $closing->period_start->toDateString());
        $this->assertSame('2026-08-31', $closing->period_end->toDateString());
        $this->assertSame(80_000, $closing->theoretical_balance_cents);
        $this->assertSame(7_876_000, $closing->real_balance_cents);
        $this->assertSame(20_000, $closing->pointed_expenses_cents);
        $this->assertSame(0, $closing->pointed_incomes_cents);
        $this->assertSame(7_796_000, $closing->variance_cents);
        $this->assertSame('Oubli : péage + espèces', $closing->note);
    }

    public function test_a_closing_without_note_stores_null(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/clotures', ['account_id' => $account->id, 'real_balance' => '0,00'])
            ->assertRedirect();

        $this->assertNull(AccountClosing::sole()->note);
    }

    public function test_another_profile_account_cannot_be_closed(): void
    {
        $account = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->post('/clotures', ['account_id' => $account->id, 'real_balance' => '100'])
            ->assertSessionHasErrors('account_id');

        $this->assertSame(0, AccountClosing::count());
    }

    public function test_the_closings_history_is_listed_newest_first(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Compte principal']);

        AccountClosing::factory()->for($account)->create(['period_end' => '2026-06-30']);
        AccountClosing::factory()->for($account)
            ->balancing(210_000, 208_760)
            ->noted('Oubli : péage A10.')
            ->create(['period_end' => '2026-07-31']);

        $this->actingAs($user)
            ->get('/bilan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('closings', 2)
                ->where('closings.0.month_label', 'Juillet 2026')
                ->where('closings.0.account_name', 'Compte principal')
                ->where('closings.0.variance_cents', -1_240)
                ->where('closings.0.note', 'Oubli : péage A10.')
                ->where('closings.1.month_label', 'Juin 2026'));
    }

    public function test_another_profile_closings_are_never_listed(): void
    {
        AccountClosing::factory()
            ->for(Account::factory()->for(User::factory()->create()))
            ->create();

        $this->actingAs(User::factory()->create())
            ->get('/bilan')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('closings', 0));
    }
}
