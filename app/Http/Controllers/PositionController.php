<?php

namespace App\Http\Controllers;

use App\Actions\FetchCryptoPrices;
use App\Actions\SyncCryptoValuations;
use App\Models\Account;
use App\Models\Position;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    public function store(Request $request, Account $account, FetchCryptoPrices $fetchCryptoPrices): RedirectResponse
    {
        Gate::authorize('update', $account);

        $validated = $request->validate(
            [
                // L'identifiant CoinGecko : « bitcoin », « ethereum »…
                'asset_id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
                'quantity' => ['required', 'numeric', 'gt:0'],
            ],
            [
                'asset_id.required' => 'Indiquez l\'identifiant CoinGecko de l\'actif.',
                'asset_id.regex' => 'L\'identifiant CoinGecko s\'écrit en minuscules : « bitcoin », « ethereum »…',
                'quantity.required' => 'Indiquez la quantité détenue.',
                'quantity.gt' => 'La quantité doit être supérieure à zéro.',
            ],
        );

        /*
         * Récupérer le cours immédiatement valide l'identifiant : un actif
         * inconnu de CoinGecko n'a rien à faire dans le portefeuille.
         */
        $updated = $fetchCryptoPrices->handle([$validated['asset_id']]);

        if ($updated === null) {
            throw ValidationException::withMessages([
                'asset_id' => 'Le service de cours ne répond pas — réessayez dans un instant.',
            ]);
        }

        if (! in_array($validated['asset_id'], $updated, true)) {
            throw ValidationException::withMessages([
                'asset_id' => "Aucun actif « {$validated['asset_id']} » chez CoinGecko. L'identifiant figure dans l'URL de sa fiche coingecko.com.",
            ]);
        }

        $account->positions()->updateOrCreate(
            ['asset_id' => $validated['asset_id']],
            ['label' => $validated['asset_id'], 'quantity' => $validated['quantity']],
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
    public function sync(Request $request, Account $account, SyncCryptoValuations $syncCryptoValuations): RedirectResponse
    {
        Gate::authorize('update', $account);

        $syncCryptoValuations->handle(CarbonImmutable::now());

        return back()->with('success', 'Cours rafraîchis, compte recalé.');
    }
}
