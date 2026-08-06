<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssetSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pea_searches_yahoo_by_fund_name_and_keeps_only_buyable_quotes(): void
    {
        Http::fake(['query2.finance.yahoo.com/*' => Http::response(['quotes' => [
            [
                'symbol' => 'EWLD.PA',
                'longname' => 'Amundi Index Solutions - Amundi MSCI World Swap UCITS ETF EUR Dist',
                'quoteType' => 'ETF',
                'exchDisp' => 'Paris',
            ],
            // Un indice ne s'achète pas : il ne doit pas être proposé.
            ['symbol' => '^990100-USD-STRD', 'shortname' => 'MSCI World Index', 'quoteType' => 'INDEX'],
        ]])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/actifs/recherche?type='.AccountType::StockPlan->value.'&q=amundi msci world')
            ->assertOk()
            ->assertExactJson([[
                'asset_id' => 'EWLD.PA',
                'label' => 'Amundi Index Solutions - Amundi MSCI World Swap UCITS ETF EUR Dist',
                'detail' => 'Paris',
            ]]);
    }

    public function test_a_crypto_account_searches_coingecko(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response(['coins' => [
            ['id' => 'bitcoin', 'name' => 'Bitcoin', 'symbol' => 'btc'],
        ]])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/actifs/recherche?type='.AccountType::Crypto->value.'&q=bitco')
            ->assertOk()
            ->assertExactJson([['asset_id' => 'bitcoin', 'label' => 'Bitcoin', 'detail' => 'BTC']]);
    }

    public function test_a_broken_search_api_degrades_to_an_empty_list(): void
    {
        Http::fake(['query2.finance.yahoo.com/*' => Http::response(null, 500)]);

        $this->actingAs(User::factory()->create())
            ->getJson('/actifs/recherche?type='.AccountType::StockPlan->value.'&q=amundi')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_the_search_is_reserved_for_market_account_types(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/actifs/recherche?type='.AccountType::Current->value.'&q=amundi')
            ->assertNotFound();
    }
}
