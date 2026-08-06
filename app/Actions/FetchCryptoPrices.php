<?php

namespace App\Actions;

use App\Models\AssetPrice;
use Illuminate\Support\Facades\Http;

final class FetchCryptoPrices
{
    /**
     * L'API publique et gratuite de CoinGecko — un appel unique pour tous les
     * actifs, aucune clé, aucune donnée personnelle : seuls les identifiants
     * d'actifs partent sur le réseau, jamais les quantités détenues.
     */
    private const PRICE_URL = 'https://api.coingecko.com/api/v3/simple/price';

    /**
     * Rafraîchit le dernier cours connu des actifs demandés.
     *
     * En cas de panne ou de limite de débit, les cours précédents restent en
     * place — l'interface montre leur âge — et l'échec est signalé par le
     * retour null, sans exception : la synchronisation quotidienne réessaiera
     * le lendemain.
     *
     * @param  list<string>  $assetIds
     * @return list<string>|null Identifiants effectivement mis à jour, ou null si l'API n'a pas répondu
     */
    public function handle(array $assetIds): ?array
    {
        if ($assetIds === []) {
            return [];
        }

        $response = Http::timeout(15)
            ->retry(2, 1000, throw: false)
            ->get(self::PRICE_URL, [
                'ids' => implode(',', $assetIds),
                'vs_currencies' => 'eur',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $updated = [];

        foreach ($response->json() as $assetId => $quote) {
            if (! isset($quote['eur'])) {
                continue;
            }

            AssetPrice::updateOrCreate(
                ['asset_id' => $assetId],
                ['price_eur' => $quote['eur'], 'fetched_at' => now()],
            );

            $updated[] = $assetId;
        }

        return $updated;
    }
}
