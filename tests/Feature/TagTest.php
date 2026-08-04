<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_counts_the_operations_of_each_tag(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->ofType(AccountType::Current)->create();
        $groceries = Tag::factory()->for($account)->named('Courses')->create();
        Tag::factory()->for($account)->named('Loyer')->create();

        Transaction::factory()->for($account)->for($groceries)->count(3)->create();

        $this->actingAs($user)
            ->get('/tags')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tags/Index')
                ->where('selected_account_id', $account->id)
                ->where('type_note', 'Tags par défaut du type « Compte courant », plus les vôtres.')
                ->has('tags', 2)
                ->where('tags.0.name', 'Courses')
                ->where('tags.0.transactions_count', 3)
                ->where('tags.1.transactions_count', 0));
    }

    public function test_the_account_given_in_the_query_drives_the_list(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();
        $second = Account::factory()->for($user)->ofType(AccountType::Crypto)->create();
        Tag::factory()->for($second)->named('Staking')->create();

        $this->actingAs($user)
            ->get("/tags?compte={$second->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected_account_id', $second->id)
                ->has('tags', 1)
                ->where('tags.0.name', 'Staking'));
    }

    public function test_a_tag_can_be_added(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/tags', ['account_id' => $account->id, 'name' => 'Vacances'])
            ->assertRedirect();

        $this->assertSame('Vacances', $account->tags()->sole()->name);
    }

    public function test_the_same_tag_cannot_be_added_twice_on_one_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Tag::factory()->for($account)->named('Courses')->create();

        $this->actingAs($user)
            ->post('/tags', ['account_id' => $account->id, 'name' => 'Courses'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $account->tags()->count());
    }

    public function test_the_same_name_is_allowed_on_two_different_accounts(): void
    {
        $user = User::factory()->create();
        $first = Account::factory()->for($user)->create();
        $second = Account::factory()->for($user)->create();
        Tag::factory()->for($first)->named('Courses')->create();

        $this->actingAs($user)
            ->post('/tags', ['account_id' => $second->id, 'name' => 'Courses'])
            ->assertRedirect();

        $this->assertSame('Courses', $second->tags()->sole()->name);
    }

    public function test_adding_a_tag_to_another_profile_account_is_refused(): void
    {
        $foreignAccount = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->post('/tags', ['account_id' => $foreignAccount->id, 'name' => 'Courses'])
            ->assertSessionHasErrors('account_id');
    }

    public function test_deleting_a_tag_keeps_its_operations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $tag = Tag::factory()->for($account)->named('Courses')->create();
        $transaction = Transaction::factory()->for($account)->for($tag)->create();

        $this->actingAs($user)
            ->delete("/tags/{$tag->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertNull($transaction->fresh()->tag_id);
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_deleting_another_profile_tag_is_refused(): void
    {
        $foreignAccount = Account::factory()->for(User::factory()->create())->create();
        $tag = Tag::factory()->for($foreignAccount)->create();

        $this->actingAs(User::factory()->create())
            ->delete("/tags/{$tag->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }
}
