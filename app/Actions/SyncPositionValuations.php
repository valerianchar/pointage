<?php

namespace App\Actions;

use App\Enums\AssetProvider;
use App\Models\Account;
use App\Models\AssetPrice;
use App\Models\Position;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class SyncPositionValuations
{
    public function __construct(
        private readonly FetchCryptoPrices $fetchCryptoPrices,
        private readonly FetchEtfPrices $fetchEtfPrices,
        private readonly RevalueAccount $revalueAccount,
    ) {}

    /**
     * Recale chaque compte à positions — crypto comme PEA — sur la valeur de
     * son portefeuille au dernier cours : rafraîchit les cours chez chaque
     * fournisseur, puis crée l'opération de réévaluation des comptes dont le
     * solde a dérivé.
     *
     * Un compte n'est recalé que si TOUS ses actifs ont un cours connu — mieux
     * vaut un solde d'hier qu'un solde faux d'aujourd'hui.
     *
     * @return int Nombre de comptes réévalués
     */
    public function handle(CarbonInterface $today): int
    {
        $positions = Position::query()->with('account')->get();

        if ($positions->isEmpty()) {
            return 0;
        }

        /*
         * Chaque fournisseur reçoit ses actifs à lui : une panne de l'un
         * n'empêche pas les comptes de l'autre de se recaler.
         */
        foreach ($positions->groupBy(fn (Position $position): string => $position->provider->value) as $providerValue => $providerPositions) {
            $assetIds = $providerPositions->pluck('asset_id')->unique()->values()->all();

            match (AssetProvider::from($providerValue)) {
                AssetProvider::Coingecko => $this->fetchCryptoPrices->handle($assetIds),
                AssetProvider::Yahoo => $this->fetchEtfPrices->handle($assetIds),
            };
        }

        $prices = AssetPrice::query()
            ->whereIn('asset_id', $positions->pluck('asset_id'))
            ->get()
            ->keyBy(fn (AssetPrice $price): string => $price->provider->value.':'.$price->asset_id);

        $revaluedCount = 0;

        foreach ($positions->groupBy('account_id') as $accountPositions) {
            $targetCents = $this->portfolioValueCents($accountPositions, $prices);

            if ($targetCents === null) {
                continue;
            }

            /** @var Account $account */
            $account = $accountPositions->first()->account;

            if ($this->revalueAccount->handle($account, $targetCents, $today) !== null) {
                $revaluedCount++;
            }
        }

        return $revaluedCount;
    }

    /**
     * @param  Collection<int, Position>  $accountPositions
     * @param  Collection<string, AssetPrice>  $prices
     */
    private function portfolioValueCents(Collection $accountPositions, Collection $prices): ?int
    {
        $totalCents = 0;

        foreach ($accountPositions as $position) {
            $valueCents = $position->valueCents(
                $prices->get($position->provider->value.':'.$position->asset_id)
            );

            if ($valueCents === null) {
                return null;
            }

            $totalCents += $valueCents;
        }

        return $totalCents;
    }
}
