<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePointingPeriodRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AccountPointingPeriodController extends Controller
{
    public function update(UpdatePointingPeriodRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $account->update([
            'period_start_day' => $request->integer('period_start_day'),
            'period_end_day' => $request->integer('period_end_day'),
        ]);

        return back()->with('success', 'Période de pointage enregistrée.');
    }
}
