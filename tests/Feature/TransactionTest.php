<?php

namespace Tests\Feature;

use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_ships_every_account_with_its_tags(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Tag::factory()->for($account)->named('Courses')->create();

        $this->actingAs($user)
            ->get('/ajouter')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transactions/Create')
                ->has('accounts', 1)
                ->has('accounts.0.tags', 1)
                ->where('accounts.0.tags.0.name', 'Courses')
                ->has('directions', 2)
                ->where('selected_account_id', $account->id));
    }

    public function test_the_account_given_in_the_query_is_preselected(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();
        $second = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->get("/ajouter?compte={$second->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('selected_account_id', $second->id));
    }

    public function test_an_expense_is_recorded_negative_and_lowers_the_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->startingAt(200_000)->create();
        $tag = Tag::factory()->for($account)->named('Courses')->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '64,90',
                'label' => 'Carrefour',
                'tag_id' => $tag->id,
                'is_recurring' => false,
            ])
            ->assertRedirect("/compte/{$account->id}");

        $transaction = $account->transactions()->sole();

        $this->assertSame(-6_490, $transaction->amount_cents);
        $this->assertSame('Carrefour', $transaction->label);
        $this->assertNull($transaction->pointed_at);
        $this->assertSame(193_510, $account->fresh()->balance_cents);
    }

    public function test_an_addition_is_recorded_positive(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Income->value,
                'amount' => '2400',
                'label' => 'Salaire',
                'is_recurring' => false,
            ])
            ->assertRedirect();

        $this->assertSame(240_000, $account->transactions()->sole()->amount_cents);
    }

    public function test_a_recurring_operation_also_creates_its_monthly_template(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '10,99',
                'label' => 'Spotify',
                'is_recurring' => true,
            ])
            ->assertRedirect();

        $template = $account->recurringTransactions()->sole();
        $transaction = $account->transactions()->sole();

        $this->assertSame(-1_099, $template->amount_cents);
        $this->assertSame('Spotify', $template->label);
        $this->assertTrue($template->is_active);
        $this->assertSame($template->id, $transaction->recurring_transaction_id);
    }

    public function test_a_one_off_operation_creates_no_template(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '12',
                'label' => 'Boulangerie',
                'is_recurring' => false,
            ])
            ->assertRedirect();

        $this->assertSame(0, $account->recurringTransactions()->count());
        $this->assertNull($account->transactions()->sole()->recurring_transaction_id);
    }

    public function test_an_unreadable_amount_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => 'beaucoup',
                'label' => 'Carrefour',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $account->transactions()->count());
    }

    public function test_a_zero_amount_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '0',
                'label' => 'Carrefour',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_a_missing_label_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '10',
                'label' => '',
            ])
            ->assertSessionHasErrors('label');
    }

    public function test_a_tag_belonging_to_another_account_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();
        $foreignTag = Tag::factory()->for($otherAccount)->named('Loyer')->create();

        $this->actingAs($user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '10',
                'label' => 'Test',
                'tag_id' => $foreignTag->id,
            ])
            ->assertSessionHasErrors('tag_id');
    }

    public function test_writing_on_another_profile_account_is_refused(): void
    {
        $foreignAccount = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->post('/operations', [
                'account_id' => $foreignAccount->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '10',
                'label' => 'Test',
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertSame(0, $foreignAccount->transactions()->count());
    }
}
