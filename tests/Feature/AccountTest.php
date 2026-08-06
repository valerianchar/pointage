<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\AssetProvider;
use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_detail_screen_lists_the_month_and_groups_spending_by_tag(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->startingAt(200_000)->create();
        $groceries = Tag::factory()->for($account)->named('Courses')->create();

        Transaction::factory()->for($account)->for($groceries)->expense(6_490)->create();
        Transaction::factory()->for($account)->for($groceries)->expense(4_250)->create();

        $this->actingAs($user)
            ->get("/compte/{$account->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Accounts/Show')
                ->where('account.balance_cents', 189_260)
                ->has('transactions', 2)
                ->has('tag_spending', 1)
                ->where('tag_spending.0.tag', 'Courses')
                ->where('tag_spending.0.amount_cents', 10_740));
    }

    public function test_operations_left_to_point_stay_reachable_from_earlier_months(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($account)->expense(1_000)
            ->on(now()->subMonths(3)->toDateString())
            ->create();
        Transaction::factory()->for($account)->expense(2_000)->pointed()
            ->on(now()->subMonths(3)->toDateString())
            ->create();

        // La dépense pointée d'il y a trois mois sort de l'écran ; l'autre reste.
        $this->actingAs($user)
            ->get("/compte/{$account->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions', 1)
                ->where('transactions.0.is_pointed', false));
    }

    public function test_another_profile_account_is_out_of_reach(): void
    {
        $account = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->get("/compte/{$account->id}")
            ->assertForbidden();
    }

    public function test_the_creation_screen_offers_the_six_types(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/nouveau-compte')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Accounts/Create')
                ->has('types', 6)
                ->where('types.0.label', 'Compte courant')
                ->where('types.0.default_tags', ['Courses', 'Loyer', 'Transport', 'Abonnements', 'Salaire']));
    }

    public function test_declaring_an_account_creates_its_default_tags(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', [
                'name' => 'PEA Fortuneo',
                'type' => AccountType::StockPlan->value,
                'initial_balance' => '12 480,30',
            ])
            ->assertRedirect();

        $account = $user->accounts()->sole();

        $this->assertSame('PEA Fortuneo', $account->name);
        $this->assertSame(AccountType::StockPlan, $account->type);
        $this->assertSame(1_248_030, $account->initial_balance_cents);
        $this->assertSame(
            ['Achat action', 'Vente', 'Dividende', 'Frais'],
            $account->tags()->orderBy('id')->pluck('name')->all(),
        );
    }

    public function test_an_account_without_a_starting_balance_begins_at_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', ['name' => 'Livret', 'type' => AccountType::Savings->value, 'initial_balance' => ''])
            ->assertRedirect();

        $this->assertSame(0, $user->accounts()->sole()->initial_balance_cents);
    }

    public function test_a_nameless_account_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', ['name' => '', 'type' => AccountType::Current->value])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $user->accounts()->count());
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', ['name' => 'Compte', 'type' => 'coffre-fort'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, $user->accounts()->count());
    }

    public function test_a_crypto_account_is_born_at_the_value_of_its_positions(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response([
            'bitcoin' => ['eur' => 100_000],
            'ethereum' => ['eur' => 3_000],
        ])]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', [
                'name' => 'Portefeuille crypto',
                'type' => AccountType::Crypto->value,
                'positions' => [
                    ['asset_id' => 'Bitcoin', 'quantity' => '0,05'],
                    ['asset_id' => 'ethereum', 'quantity' => '2'],
                ],
            ])
            ->assertRedirect();

        $account = $user->accounts()->sole();

        // 0,05 × 100 000 € + 2 × 3 000 € = 11 000 € — et l'identifiant retombe en minuscules.
        $this->assertSame(1_100_000, $account->initial_balance_cents);
        $this->assertSame(['bitcoin', 'ethereum'], $account->positions()->orderBy('id')->pluck('asset_id')->all());
        $this->assertSame(AssetProvider::Coingecko, $account->positions()->first()->provider);
    }

    public function test_a_pea_account_declares_its_etfs_the_same_way(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response(['chart' => ['result' => [['meta' => [
            'currency' => 'EUR',
            'regularMarketPrice' => 550.25,
        ]]]]])]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', [
                'name' => 'PEA Fortuneo',
                'type' => AccountType::StockPlan->value,
                'positions' => [['asset_id' => 'cw8.pa', 'quantity' => '12']],
            ])
            ->assertRedirect();

        $account = $user->accounts()->sole();
        $position = $account->positions()->sole();

        $this->assertSame(660_300, $account->initial_balance_cents);
        $this->assertSame('CW8.PA', $position->asset_id);
        $this->assertSame(AssetProvider::Yahoo, $position->provider);
    }

    public function test_an_unknown_asset_blocks_the_account_creation(): void
    {
        // Réponse vide : CoinGecko ne connaît pas cet identifiant.
        Http::fake(['api.coingecko.com/*' => Http::response([])]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/comptes', [
                'name' => 'Portefeuille crypto',
                'type' => AccountType::Crypto->value,
                'positions' => [['asset_id' => 'pieceinventee', 'quantity' => '1']],
            ])
            ->assertSessionHasErrors('positions');

        $this->assertSame(0, $user->accounts()->count());
    }
}
