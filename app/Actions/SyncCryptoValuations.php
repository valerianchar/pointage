<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\AssetPrice;
use App\Models\Position;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class SyncCryptoValuations
{
    public function __construct(
        private readonly FetchCryptoPrices $fetchCryptoPrices,
        private readonly RevalueAccount $revalueAccount,
    ) {}

    /**
     * Recale chaque compte à positions sur la valeur de son portefeuille au
     * dernier cours : rafraîchit les cours, puis crée l'opération de
     * réévaluation des comptes dont le solde a dérivé.
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

        $this->fetchCryptoPrices->handle($positions->pluck('asset_id')->unique()->values()->all());

        $prices = AssetPrice::query()
            ->whereIn('asset_id', $positions->pluck('asset_id'))
            ->get()
            ->keyBy('asset_id');

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
            $valueCents = $position->valueCents($prices->get($position->asset_id));

            if ($valueCents === null) {
                return null;
            }

            $totalCents += $valueCents;
        }

        return $totalCents;
    }
}
