<?php

namespace Tests\Feature;

use App\Enums\DashboardWidget;
use App\Models\Account;
use App\Models\Credit;
use App\Models\RecurringTransaction;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_widget_is_shown_until_the_dashboard_is_customised(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('widgets', 11)
                ->where('widgets.0.key', DashboardWidget::Wealth->value)
                ->where('widgets.0.label', 'Patrimoine')
                ->where('widgets.0.span', 1)
                ->where('widgets.0.enabled', true)
                // Les widgets larges occupent deux des quatre colonnes.
                ->where('widgets.7.key', DashboardWidget::BalanceHistory->value)
                ->where('widgets.7.span', 2));
    }

    public function test_a_hidden_widget_is_neither_flagged_nor_computed(): void
    {
        $user = User::factory()->create([
            'dashboard_widgets' => [DashboardWidget::Wealth->value],
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('widgets.0.enabled', true)
                ->where('widgets.3.enabled', false)
                // Rien n'est calculé pour un widget masqué.
                ->missing('pointing')
                ->missing('daily_expense')
                ->missing('recurring_charge')
                ->missing('credit_totals')
                ->missing('top_expenses')
                ->etc());
    }

    public function test_the_selection_can_be_saved_and_emptied(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/widgets', ['widgets' => [DashboardWidget::Credits->value, DashboardWidget::Wealth->value]])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [DashboardWidget::Credits->value, DashboardWidget::Wealth->value],
            $user->fresh()->enabledDashboardWidgets(),
        );

        $this->patch('/widgets', ['widgets' => []]);

        $this->assertSame([], $user->fresh()->enabledDashboardWidgets());
    }

    public function test_an_unknown_widget_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/widgets', ['widgets' => ['meteo']])
            ->assertSessionHasErrors('widgets.0');

        $this->assertCount(11, $user->fresh()->enabledDashboardWidgets());
    }

    public function test_it_computes_the_savings_rate_and_the_month_pointing(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->income(200_000)->pointed()->create();
        Transaction::factory()->for($account)->expense(50_000)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('income_cents', 200_000)
                ->where('expense_cents', 50_000)
                // 2 000 − 500 sur 2 000 d'ajouts
                ->where('pointing.pending_count', 1)
                ->where('pointing.total_count', 2));
    }

    public function test_it_averages_the_spending_over_the_days_already_elapsed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 12:00'));

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($account)->expense(50_000)->on('2026-08-03')->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                // 500 € sur 10 jours écoulés, projetés sur les 31 jours d'août
                ->where('daily_expense.average_cents', 5_000)
                ->where('daily_expense.projected_cents', 155_000));
    }

    public function test_it_sums_the_recurring_charge_of_the_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $template = RecurringTransaction::factory()->for($account)->create();

        Transaction::factory()->for($account)->for($template)->expense(82_000)->create();
        Transaction::factory()->for($account)->for($template)->income(240_000)->pointed()
            ->on(now()->startOfMonth()->addDay()->toDateString())
            ->create();
        // Une opération ponctuelle ne pèse pas sur la charge récurrente.
        Transaction::factory()->for($account)->expense(9_900)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recurring_charge.charge_cents', 82_000)
                ->where('recurring_charge.pending_count', 1)
                ->where('recurring_charge.total_count', 2));
    }

    public function test_it_totals_the_credits_still_owed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Credit::factory()->for($account)->of(1_420_000, 639_000, 23_650)->create();
        Credit::factory()->for($account)->of(18_000_000, 14_230_000, 74_500)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('credit_totals.remaining_cents', 14_869_000)
                ->where('credit_totals.monthly_cents', 98_150)
                ->where('credit_totals.count', 2));
    }

    public function test_it_ranks_the_biggest_expenses_and_the_heaviest_tags(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Compte principal']);
        $rent = Tag::factory()->for($account)->named('Loyer')->create();
        $groceries = Tag::factory()->for($account)->named('Courses')->create();

        Transaction::factory()->for($account)->for($rent)->expense(82_000)->create();
        Transaction::factory()->for($account)->for($groceries)->expense(6_490)->create();
        Transaction::factory()->for($account)->for($groceries)->expense(4_250)->create();
        Transaction::factory()->for($account)->income(240_000)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('top_expenses', 3)
                ->where('top_expenses.0.amount_cents', -82_000)
                ->where('top_expenses.0.tag', 'Loyer')
                ->where('top_expenses.0.account_name', 'Compte principal')
                ->where('top_expenses.1.amount_cents', -6_490)
                // Les tags sont classés par poids : Loyer 820 €, Courses 107,40 €.
                ->has('tag_spending', 2)
                ->where('tag_spending.0.tag', 'Loyer')
                ->where('tag_spending.1.amount_cents', 10_740));
    }

    public function test_a_profile_without_income_gets_no_savings_rate(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->for(Account::factory()->for($user))->expense(5_000)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('income_cents', 0));
    }
}
