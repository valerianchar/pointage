<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_balances_month_flows_and_the_eight_week_history(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->ofType(AccountType::Current)->startingAt(100_000)->create();

        Transaction::factory()->for($account)->income(240_000)->on(now()->startOfMonth()->toDateString())->create();
        Transaction::factory()->for($account)->expense(82_000)->on(now()->startOfMonth()->toDateString())->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Home')
                ->where('income_cents', 240_000)
                ->where('expense_cents', 82_000)
                ->has('balance_history', 8)
                ->has('accounts', 1)
                // 100 000 de départ + 240 000 − 82 000
                ->where('accounts.0.balance_cents', 258_000)
                ->where('accounts.0.pending_count', 2));
    }

    public function test_flows_from_other_months_are_left_out(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)
            ->income(50_000)
            ->on(now()->startOfMonth()->subMonth()->toDateString())
            ->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('income_cents', 0));
    }

    public function test_another_profile_accounts_are_never_listed(): void
    {
        $user = User::factory()->create();
        Account::factory()->for(User::factory()->create())->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('accounts', 0));
    }

    public function test_a_profile_without_any_account_still_gets_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Home')
                ->has('accounts', 0)
                ->where('income_cents', 0)
                ->has('balance_history', 8));
    }
}
