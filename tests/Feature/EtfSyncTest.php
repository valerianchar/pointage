<?php

namespace Tests\Feature;

use App\Actions\SyncPositionValuations;
use App\Enums\AccountType;
use App\Enums\AssetProvider;
use App\Models\Account;
use App\Models\Position;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EtfSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed> Réponse de l'API de graphiques Yahoo, réduite à ce qu'on lit
     */
    private function yahooChart(float $price, string $currency = 'EUR'): array
    {
        return ['chart' => ['result' => [['meta' => [
            'currency' => $currency,
            'regularMarketPrice' => $price,
        ]]]]];
    }

    public function test_the_sync_revalues_a_pea_to_its_etf_portfolio_value(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response($this->yahooChart(550.25))]);

        $account = Account::factory()->ofType(AccountType::StockPlan)->startingAt(500_000)->create();
        Position::factory()->for($account)->etf('CW8.PA', '12')->create();

        $revalued = app(SyncPositionValuations::class)->handle(CarbonImmutable::now());

        // 12 × 550,25 € = 6 603 €.
        $this->assertSame(1, $revalued);
        $this->assertSame(660_300, $account->fresh()->balance_cents);
        $this->assertTrue($account->transactions()->sole()->is_revaluation);
    }

    public function test_a_coingecko_outage_does_not_stop_the_pea_from_revaluing(): void
    {
        Http::fake([
            'api.coingecko.com/*' => Http::response(null, 503),
            'query1.finance.yahoo.com/*' => Http::response($this->yahooChart(100)),
        ]);

        $crypto = Account::factory()->ofType(AccountType::Crypto)->startingAt(0)->create();
        Position::factory()->for($crypto)->of('bitcoin', '1')->create();

        $pea = Account::factory()->ofType(AccountType::StockPlan)->startingAt(0)->create();
        Position::factory()->for($pea)->etf('CW8.PA', '5')->create();

        $this->assertSame(1, app(SyncPositionValuations::class)->handle(CarbonImmutable::now()));
        $this->assertSame(0, $crypto->fresh()->balance_cents);
        $this->assertSame(50_000, $pea->fresh()->balance_cents);
    }

    public function test_declaring_an_etf_position_normalizes_and_validates_the_ticker(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response($this->yahooChart(550.25))]);

        $account = Account::factory()->ofType(AccountType::StockPlan)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'cw8.pa', 'quantity' => '2,5'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $position = $account->positions()->sole();
        $this->assertSame('CW8.PA', $position->asset_id);
        $this->assertSame(AssetProvider::Yahoo, $position->provider);
        $this->assertSame('2.5000000000', $position->quantity);
    }

    public function test_a_ticker_quoted_outside_euros_is_refused(): void
    {
        // Coté en dollars : le portefeuille se tient en euros, on refuse.
        Http::fake(['query1.finance.yahoo.com/*' => Http::response($this->yahooChart(430.10, 'USD'))]);

        $account = Account::factory()->ofType(AccountType::StockPlan)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'SPY', 'quantity' => '1'])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame(0, $account->positions()->count());
    }

    public function test_an_unknown_ticker_is_refused_without_breaking_the_sync_contract(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response(null, 404)]);

        $account = Account::factory()->ofType(AccountType::StockPlan)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'NIMPORTEQUOI.PA', 'quantity' => '1'])
            ->assertSessionHasErrors('asset_id');
    }

    public function test_positions_are_reserved_for_market_account_types(): void
    {
        Http::fake();

        $account = Account::factory()->ofType(AccountType::Current)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/positions", ['asset_id' => 'bitcoin', 'quantity' => '1'])
            ->assertNotFound();
    }
}
