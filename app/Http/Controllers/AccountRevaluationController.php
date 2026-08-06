<?php

namespace App\Http\Controllers;

use App\Actions\RevalueAccount;
use App\Models\Account;
use App\Rules\ValidAmount;
use App\Support\Amount;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountRevaluationController extends Controller
{
    public function store(Request $request, Account $account, RevalueAccount $revalueAccount): RedirectResponse
    {
        Gate::authorize('update', $account);

        $validated = $request->validate(
            // Un compte de marché peut valoir moins que zéro sur marge : accepté.
            ['current_value' => ['required', new ValidAmount]],
            ['current_value.required' => 'Saisissez la valeur actuelle du compte.'],
        );

        $transaction = $revalueAccount->handle(
            $account,
            Amount::toCents($validated['current_value']) ?? 0,
            CarbonImmutable::now(),
        );

        return back()->with('success', $transaction === null
            ? 'Le solde correspond déjà : rien à réévaluer.'
            : 'Compte réévalué.');
    }
}
