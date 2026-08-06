<?php

namespace Tests\Feature;

use App\Actions\SyncCryptoValuations;
use App\Models\Account;
use App\Models\AssetPrice;
use App\Models\Position;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CryptoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sync_revalues_the_account_to_its_portfolio_value(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response([
            'bitcoin' => ['eur' => 100_000],
            'ethereum' => ['eur' => 3_000.50],
        ])]);

        $account = Account::factory()->startingAt(500_000)->create();
        Position::factory()->for($account)->of('bitcoin', '0.05')->create();
        Position::factory()->for($account)->of('ethereum', '2')->create();

        $revalued = app(SyncCryptoValuations::class)->handle(CarbonImmutable::now());

        // 0,05 × 100 000 € + 2 × 3 000,50 € = 11 001 €.
        $this->assertSame(1, $revalued);
        $this->assertSame(1_100_100, $account->fresh()->balance_cents);

        $revaluation = $account->transactions()->sole();
        $this->assertTrue($revaluation->is_revaluation);
        $this->assertSame(1_100_100 - 500_000, $revaluation->amount_cents);
    }

    public function test_an_unchanged_portfolio_creates_no_daily_noise(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response(['bitcoin' => ['eur' => 100_000]])]);

        $account = Account::factory()->startingAt(0)->create();
        Position::factory()->for($account)->of('bitcoin', '0.05')->create();

        $sync = app(SyncCryptoValuations::class);
        $this->assertSame(1, $sync->handle(CarbonImmutable::now()));
        $this->assertSame(0, $sync->handle(CarbonImmutable::now()));
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_a_failing_price_api_leaves_the_account_untouched(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response(null, 503)]);

        $account = Account::factory()->startingAt(500_000)->create();
        Position::factory()->for($account)->of('bitcoin', '0.05')->create();

        $this->assertSame(0, app(SyncCryptoValuations::class)->handle(CarbonImmutable::now()));
        $this->assertSame(0, $account->transactions()->count());
        $this->assertSame(500_000, $account->fresh()->balance_cents);
    }

    public function test_a_stale_price_still_revalues_after_an_api_outage(): void
    {
        // Cours d'hier en base, API en panne aujourd'hui : on recale sur l'existant.
        AssetPrice::create(['asset_id' => 'bitcoin', 'price_eur' => 90_000, 'fetched_at' => now()->subDay()]);
        Http::fake(['api.coingecko.com/*' => Http::response(null, 503)]);

        $account = Account::factory()->startingAt(0)->create();
        Position::factory()->for($account)->of('bitcoin', '0.1')->create();

        $this->assertSame(1, app(SyncCryptoValuations::class)->handle(CarbonImmutable::now()));
        $this->assertSame(900_000, $account->fresh()->balance_cents);
    }

    public function test_declaring_a_position_validates_the_asset_against_the_api(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response(['bitcoin' => ['eur' => 100_000]])]);

        $account = Account::factory()->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'bitcoin', 'quantity' => '0.05'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('0.0500000000', $account->positions()->sole()->quantity);

        // Un actif inconnu de CoinGecko est refusé (réponse vide pour cet id).
        Http::fake(['api.coingecko.com/*' => Http::response([])]);
        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'pieceinventee', 'quantity' => '1'])
            ->assertSessionHasErrors('asset_id');
    }

    public function test_someone_else_cannot_manage_positions(): void
    {
        Http::fake();
        $account = Account::factory()->create();
        $position = Position::factory()->for($account)->create();

        $this->actingAs(User::factory()->create())
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'bitcoin', 'quantity' => '1'])
            ->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->delete("/positions/{$position->id}")
            ->assertForbidden();

        $this->assertSame(1, $account->positions()->count());
    }
}
