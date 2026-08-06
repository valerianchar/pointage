<?php

namespace App\Actions;

use App\Enums\AssetProvider;
use App\Models\AssetPrice;
use Illuminate\Support\Facades\Http;

final class FetchEtfPrices
{
    /**
     * L'API de graphiques de Yahoo Finance — non documentée mais publique et
     * sans clé, la seule gratuite qui cote les ETF d'Euronext. Un appel par
     * ticker : elle n'a pas d'équivalent du lot CoinGecko. Comme pour les
     * cryptos, seuls les tickers partent sur le réseau, jamais les quantités.
     */
    private const CHART_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    /**
     * Rafraîchit le dernier cours connu des tickers demandés.
     *
     * Même contrat que les cours crypto : en cas de panne, les cours précédents
     * restent en place et l'échec se signale par null. Un ticker inconnu — ou
     * coté dans une autre devise que l'euro — est simplement absent de la
     * liste retournée.
     *
     * @param  list<string>  $tickers
     * @return list<string>|null Tickers effectivement mis à jour, ou null si l'API n'a pas répondu
     */
    public function handle(array $tickers): ?array
    {
        if ($tickers === []) {
            return [];
        }

        $updated = [];

        foreach ($tickers as $ticker) {
            /*
             * Sans en-tête de navigateur, Yahoo répond 429 d'office ; avec, la
             * limite est large — largement de quoi coter un portefeuille par jour.
             */
            $response = Http::timeout(15)
                ->retry(2, 1000, throw: false)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)'])
                ->get(self::CHART_URL.$ticker, ['range' => '1d', 'interval' => '1d']);

            // Un ticker inconnu répond 404 : ce n'est pas une panne, on continue.
            if ($response->status() === 404) {
                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            $meta = $response->json('chart.result.0.meta');

            if (! isset($meta['regularMarketPrice']) || ($meta['currency'] ?? null) !== 'EUR') {
                continue;
            }

            AssetPrice::updateOrCreate(
                ['provider' => AssetProvider::Yahoo, 'asset_id' => $ticker],
                ['price_eur' => $meta['regularMarketPrice'], 'fetched_at' => now()],
            );

            $updated[] = $ticker;
        }

        return $updated;
    }
}
