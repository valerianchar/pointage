<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    /**
     * Mode confidentialité : tous les montants deviennent « ••••• € » à l'écran.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hide_balances' => ['required', 'boolean'],
        ]);

        $request->user()->update($validated);

        return back();
    }
}
