<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Abonnement du navigateur aux rappels de pointage.
 *
 * Un utilisateur peut avoir plusieurs abonnements — le téléphone et le poste de
 * travail — chacun identifié par l'endpoint que fournit son navigateur.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
        );

        return back()->with('success', 'Rappels de pointage activés sur cet appareil.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return back()->with('success', 'Rappels désactivés sur cet appareil.');
    }
}
