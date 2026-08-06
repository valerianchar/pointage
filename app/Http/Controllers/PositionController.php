<?php

namespace App\Http\Controllers;

use App\Actions\EnsureAssetPrices;
use App\Actions\SyncPositionValuations;
use App\Models\Account;
use App\Models\Position;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PositionController extends Controller
{
    public function store(Request $request, Account $account, EnsureAssetPrices $ensureAssetPrices): RedirectResponse
    {
        Gate::authorize('update', $account);

        // Seuls les types à positions ont un fournisseur de cours : crypto, PEA.
        $provider = $account->type->assetProvider();
        abort_if($provider === null, 404);

        // La quantité arrive comme on la tape : virgule décimale, espaces.
        if (is_string($request->input('quantity'))) {
            $request->merge(['quantity' => str_replace([' ', ','], ['', '.'], trim($request->input('quantity')))]);
        }

        $validated = $request->validate(
            [
                'asset_id' => $provider->assetIdRules(),
                'quantity' => ['required', 'numeric', 'gt:0'],
            ],
            [
                'asset_id.required' => 'Indiquez l\'identifiant de l\'actif.',
                'asset_id.regex' => $provider->assetIdFormatMessage(),
                'quantity.required' => 'Indiquez la quantité détenue.',
                'quantity.gt' => 'La quantité doit être supérieure à zéro.',
            ],
        );

        /*
         * Récupérer le cours immédiatement valide l'identifiant : un actif
         * inconnu du fournisseur n'a rien à faire dans le portefeuille.
         */
        $assetId = $provider->normalizeAssetId($validated['asset_id']);
        $ensureAssetPrices->handle($provider, [$assetId]);

        $account->positions()->updateOrCreate(
            ['asset_id' => $assetId],
            ['provider' => $provider, 'label' => $assetId, 'quantity' => $validated['quantity']],
        );

        return back()->with('success', 'Position enregistrée — le compte suivra son cours chaque nuit.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        Gate::authorize('update', $position->account);

        $position->delete();

        return back()->with('success', 'Position supprimée.');
    }

    /**
     * Synchronisation à la demande : mêmes gestes que le passage nocturne.
     */
    public function sync(Request $request, Account $account, SyncPositionValuations $syncPositionValuations): RedirectResponse
    {
        Gate::authorize('update', $account);

        $syncPositionValuations->handle(CarbonImmutable::now());

        return back()->with('success', 'Cours rafraîchis, compte recalé.');
    }
}
