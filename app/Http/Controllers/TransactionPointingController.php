<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TransactionPointingController extends Controller
{
    /**
     * Bascule le pointage d'une opération : rapprochée du relevé, ou de nouveau à pointer.
     */
    public function update(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $transaction->update([
            'pointed_at' => $transaction->isPointed() ? null : now(),
        ]);

        return back();
    }
}
