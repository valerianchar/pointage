<?php

namespace App\Actions;

use App\Enums\AssetProvider;
use App\Models\AssetPrice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EnsureAssetPrices
{
    public function __construct(
        private readonly FetchCryptoPrices $fetchCryptoPrices,
        private readonly FetchEtfPrices $fetchEtfPrices,
    ) {}

    /**
     * Récupère le cours du jour des actifs demandés, et par là même les
     * valide : un actif inconnu du fournisseur n'a rien à faire dans un
     * portefeuille.
     *
     * @param  list<string>  $assetIds  Identifiants déjà normalisés
     * @return Collection<string, AssetPrice> Cours indexés par identifiant d'actif
     *
     * @throws ValidationException Fournisseur muet ou actif inconnu, sur le champ `$errorField`
     */
    public function handle(AssetProvider $provider, array $assetIds, string $errorField = 'asset_id'): Collection
    {
        $updated = match ($provider) {
            AssetProvider::Coingecko => $this->fetchCryptoPrices->handle($assetIds),
            AssetProvider::Yahoo => $this->fetchEtfPrices->handle($assetIds),
        };

        if ($updated === null) {
            throw ValidationException::withMessages([
                $errorField => 'Le service de cours ne répond pas — réessayez dans un instant.',
            ]);
        }

        foreach ($assetIds as $assetId) {
            if (! in_array($assetId, $updated, true)) {
                throw ValidationException::withMessages([
                    $errorField => $provider->unknownAssetMessage($assetId),
                ]);
            }
        }

        return AssetPrice::query()
            ->where('provider', $provider)
            ->whereIn('asset_id', $assetIds)
            ->get()
            ->keyBy('asset_id');
    }
}
