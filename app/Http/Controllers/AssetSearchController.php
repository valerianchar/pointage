<?php

namespace App\Http\Controllers;

use App\Actions\SearchAssets;
use App\Enums\AccountType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetSearchController extends Controller
{
    /**
     * Suggestions d'actifs pour les champs de position, chez le fournisseur de
     * cours du type de compte : CoinGecko en crypto, Yahoo Finance en PEA.
     */
    public function __invoke(Request $request, SearchAssets $searchAssets): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(AccountType::class)],
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $provider = AccountType::from($validated['type'])->assetProvider();
        abort_if($provider === null, 404);

        return response()->json($searchAssets->handle($provider, $validated['q']));
    }
}
