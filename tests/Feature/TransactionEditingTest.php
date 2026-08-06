<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_edit_screen_presents_the_operation_and_its_account_tags(): void
    {
        $account = Account::factory()->create();
        $tag = Tag::factory()->for($account)->create(['name' => 'Courses']);
        $transaction = Transaction::factory()->for($account)->create([
            'label' => 'Carrefour',
            'amount_cents' => -6_490,
            'tag_id' => $tag->id,
        ]);

        $this->actingAs($account->user)
            ->get("/operations/{$transaction->id}/modifier")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transactions/Edit')
                ->where('transaction.label', 'Carrefour')
                ->where('transaction.amount_cents', -6_490)
                ->where('transaction.tag_id', $tag->id)
                ->where('account.tags.0.name', 'Courses'));
    }

    public function test_an_operation_can_be_corrected(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->for($account)->create([
            'label' => 'Carrefou',
            'amount_cents' => -6_490,
            'occurred_on' => '2026-08-03',
        ]);

        $this->actingAs($account->user)
            ->patch("/operations/{$transaction->id}", [
                'direction' => 'depense',
                'amount' => '64,90',
                'label' => 'Carrefour',
                'occurred_on' => '2026-08-04',
            ])
            ->assertRedirect("/compte/{$account->id}");

        $transaction->refresh();
        $this->assertSame('Carrefour', $transaction->label);
        $this->assertSame(-6_490, $transaction->amount_cents);
        $this->assertSame('2026-08-04', $transaction->occurred_on->toDateString());
        $this->assertNull($transaction->tag_id);
    }

    public function test_switching_the_direction_flips_the_sign_and_the_balance(): void
    {
        $account = Account::factory()->startingAt(100_000)->create();
        $transaction = Transaction::factory()->for($account)->create(['amount_cents' => -5_000]);

        $this->actingAs($account->user)
            ->patch("/operations/{$transaction->id}", [
                'direction' => 'ajout',
                'amount' => '50',
                'label' => $transaction->label,
                'occurred_on' => $transaction->occurred_on->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(5_000, $transaction->fresh()->amount_cents);
        $this->assertSame(105_000, $account->fresh()->balance_cents);
    }

    public function test_a_tag_from_another_account_is_rejected(): void
    {
        $account = Account::factory()->create();
        $foreignTag = Tag::factory()->create(['name' => 'Ailleurs']);
        $transaction = Transaction::factory()->for($account)->create();

        $this->actingAs($account->user)
            ->patch("/operations/{$transaction->id}", [
                'direction' => 'depense',
                'amount' => '10',
                'label' => 'Test',
                'occurred_on' => '2026-08-03',
                'tag_id' => $foreignTag->id,
            ])
            ->assertSessionHasErrors('tag_id');
    }

    public function test_an_operation_can_be_deleted_and_the_balance_recovers(): void
    {
        $account = Account::factory()->startingAt(100_000)->create();
        $transaction = Transaction::factory()->for($account)->create(['amount_cents' => -8_200]);

        $this->assertSame(91_800, $account->fresh()->balance_cents);

        $this->actingAs($account->user)
            ->delete("/operations/{$transaction->id}")
            ->assertRedirect("/compte/{$account->id}");

        $this->assertSame(0, $account->transactions()->count());
        $this->assertSame(100_000, $account->fresh()->balance_cents);
    }

    public function test_deleting_a_recurring_instance_keeps_its_template(): void
    {
        $account = Account::factory()->create();
        $template = $account->recurringTransactions()->create([
            'label' => 'Loyer',
            'amount_cents' => -82_000,
            'day_of_month' => 2,
        ]);
        $transaction = Transaction::factory()->for($account)->create([
            'recurring_transaction_id' => $template->id,
        ]);

        $this->actingAs($account->user)
            ->delete("/operations/{$transaction->id}")
            ->assertRedirect();

        $this->assertSame(0, $account->transactions()->count());
        $this->assertNotNull($template->fresh());
    }

    public function test_someone_else_s_operation_is_untouchable(): void
    {
        $transaction = Transaction::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get("/operations/{$transaction->id}/modifier")->assertForbidden();
        $this->actingAs($intruder)->patch("/operations/{$transaction->id}", [
            'direction' => 'depense',
            'amount' => '10',
            'label' => 'Piratage',
            'occurred_on' => '2026-08-03',
        ])->assertForbidden();
        $this->actingAs($intruder)->delete("/operations/{$transaction->id}")->assertForbidden();

        $this->assertNotNull($transaction->fresh());
    }
}
