<?php

namespace App\Actions;

use App\Enums\AssetProvider;
use Illuminate\Support\Facades\Http;

final class SearchAssets
{
    /**
     * Retrouve un actif par son nom — « amundi msci world » plutôt que
     * « EWLD.PA » : on connaît le nom de son fonds, rarement son ticker.
     *
     * Seul le texte tapé part chez le fournisseur, jamais les quantités ni le
     * portefeuille. En cas de panne, la liste revient vide : la saisie directe
     * de l'identifiant reste toujours possible.
     *
     * @return list<array{asset_id: string, label: string, detail: string}>
     */
    public function handle(AssetProvider $provider, string $query): array
    {
        return match ($provider) {
            AssetProvider::Coingecko => $this->searchCoingecko($query),
            AssetProvider::Yahoo => $this->searchYahoo($query),
        };
    }

    /**
     * @return list<array{asset_id: string, label: string, detail: string}>
     */
    private function searchCoingecko(string $query): array
    {
        $response = Http::timeout(8)
            ->retry(1, 500, throw: false)
            ->get('https://api.coingecko.com/api/v3/search', ['query' => $query]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('coins') ?? [])
            ->take(8)
            ->map(fn (array $coin): array => [
                'asset_id' => $coin['id'],
                'label' => $coin['name'] ?? $coin['id'],
                'detail' => mb_strtoupper($coin['symbol'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{asset_id: string, label: string, detail: string}>
     */
    private function searchYahoo(string $query): array
    {
        $response = Http::timeout(8)
            ->retry(1, 500, throw: false)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)'])
            ->get('https://query2.finance.yahoo.com/v1/finance/search', [
                'q' => $query,
                'quotesCount' => 8,
                'newsCount' => 0,
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('quotes') ?? [])
            // Les résultats mêlent indices, devises, futures : on garde les titres achetables.
            ->filter(fn (array $quote): bool => isset($quote['symbol'])
                && in_array($quote['quoteType'] ?? '', ['ETF', 'EQUITY', 'MUTUALFUND'], true))
            ->take(8)
            ->map(fn (array $quote): array => [
                'asset_id' => $quote['symbol'],
                'label' => $quote['longname'] ?? $quote['shortname'] ?? $quote['symbol'],
                'detail' => $quote['exchDisp'] ?? $quote['exchange'] ?? '',
            ])
            ->values()
            ->all();
    }
}
