<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UnlockRequest;
use App\Support\AppLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppLockController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! AppLock::isEngaged()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Lock', [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    }

    public function store(): RedirectResponse
    {
        AppLock::engage();

        return redirect()->route('lock.show');
    }

    public function destroy(UnlockRequest $request): RedirectResponse
    {
        AppLock::release();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Lève le verrou après une confirmation biométrique sur l'appareil.
     *
     * Le verrou masque l'affichage d'une session déjà authentifiée : c'est le
     * cookie de session qui porte l'identité, et la biométrie ne fait que
     * confirmer la présence du porteur, comme un verrouillage d'écran. Un accès
     * réellement protégé passe par la connexion, pas par cet écran.
     */
    public function destroyAfterDeviceConfirmation(): RedirectResponse
    {
        AppLock::release();

        return redirect()->route('dashboard');
    }
}
