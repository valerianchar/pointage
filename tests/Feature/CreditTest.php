<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_lists_the_credits_with_their_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Compte principal']);
        Credit::factory()->for($account)->named('Prêt auto')->of(1_420_000, 639_000, 23_650)->create();

        $this->actingAs($user)
            ->get('/credits')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Credits/Index')
                ->has('credits', 1)
                ->where('credits.0.name', 'Prêt auto')
                ->where('credits.0.account_name', 'Compte principal')
                ->where('credits.0.remaining_cents', 639_000)
                ->where('credits.0.monthly_cents', 23_650)
                // (14 200 − 6 390) / 14 200
                ->where('credits.0.repaid_percent', 55));
    }

    public function test_another_profile_credits_are_never_listed(): void
    {
        Credit::factory()->for(Account::factory()->for(User::factory()->create()))->create();

        $this->actingAs(User::factory()->create())
            ->get('/credits')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('credits', 0));
    }

    public function test_a_credit_can_be_declared_with_amounts_written_the_french_way(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt immobilier',
                'borrowed' => '180 000',
                'remaining' => '142 300,00',
                'monthly' => '745,00 €',
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame('Prêt immobilier', $credit->name);
        $this->assertSame(18_000_000, $credit->borrowed_cents);
        $this->assertSame(14_230_000, $credit->remaining_cents);
        $this->assertSame(74_500, $credit->monthly_cents);
        $this->assertSame(21, $credit->repaid_percent);
    }

    public function test_giving_only_the_remaining_capital_starts_the_credit_at_nothing_repaid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt travaux',
                'remaining' => '9 000',
                'monthly' => '150',
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame(900_000, $credit->borrowed_cents);
        $this->assertSame(900_000, $credit->remaining_cents);
        $this->assertSame(0, $credit->repaid_percent);
    }

    public function test_giving_only_the_borrowed_capital_assumes_nothing_repaid_yet(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt étudiant',
                'borrowed' => '12 000',
                'monthly' => '100',
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame(1_200_000, $credit->borrowed_cents);
        $this->assertSame(1_200_000, $credit->remaining_cents);
    }

    public function test_a_credit_without_any_capital_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', ['account_id' => $account->id, 'name' => 'Prêt', 'monthly' => '150'])
            ->assertSessionHasErrors(['borrowed', 'remaining']);

        $this->assertSame(0, $account->credits()->count());
    }

    public function test_a_remaining_capital_above_the_borrowed_one_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt',
                'borrowed' => '10 000',
                'remaining' => '12 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors('remaining');

        $this->assertSame(0, $account->credits()->count());
    }

    public function test_a_credit_without_a_monthly_payment_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', ['account_id' => $account->id, 'name' => 'Prêt', 'borrowed' => '10 000'])
            ->assertSessionHasErrors('monthly');
    }

    public function test_a_nameless_credit_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => '',
                'borrowed' => '10 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_declaring_a_credit_on_another_profile_account_is_refused(): void
    {
        $foreignAccount = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->post('/credits', [
                'account_id' => $foreignAccount->id,
                'name' => 'Prêt',
                'borrowed' => '10 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertSame(0, $foreignAccount->credits()->count());
    }

    public function test_a_credit_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $credit = Credit::factory()->for(Account::factory()->for($user))->create();

        $this->actingAs($user)
            ->delete("/credits/{$credit->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('credits', ['id' => $credit->id]);
    }

    public function test_deleting_another_profile_credit_is_refused(): void
    {
        $credit = Credit::factory()->for(Account::factory()->for(User::factory()->create()))->create();

        $this->actingAs(User::factory()->create())
            ->delete("/credits/{$credit->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('credits', ['id' => $credit->id]);
    }

    public function test_a_fully_repaid_credit_never_passes_a_hundred_percent(): void
    {
        $credit = Credit::factory()->of(1_000_000, 0, 10_000)->create();

        $this->assertSame(100, $credit->repaid_percent);
    }

    public function test_the_account_screen_shows_only_that_account_credits(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();

        Credit::factory()->for($account)->named('Prêt auto')->create();
        Credit::factory()->for($otherAccount)->named('Prêt immobilier')->create();

        $this->actingAs($user)
            ->get("/compte/{$account->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('credits', 1)
                ->where('credits.0.name', 'Prêt auto'));
    }
}
