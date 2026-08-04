<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditRequest;
use App\Http\Resources\CreditResource;
use App\Models\Account;
use App\Models\Credit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        $credits = Credit::query()
            ->whereIn('account_id', $request->user()->accounts()->select('accounts.id'))
            ->with('account')
            ->orderBy('id')
            ->get();

        return Inertia::render('Credits/Index', [
            'credits' => CreditResource::collection($credits)->resolve(),
        ]);
    }

    public function store(StoreCreditRequest $request): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        Gate::authorize('update', $account);

        $account->credits()->create([
            'name' => $request->string('name')->trim()->value(),
            'borrowed_cents' => $request->borrowedCents(),
            'remaining_cents' => $request->remainingCents(),
            'monthly_cents' => $request->monthlyCents(),
            'term_months' => $request->termMonths(),
            'payment_day' => $request->integer('payment_day'),
        ]);

        return back()->with('success', 'Crédit déclaré.');
    }

    public function destroy(Credit $credit): RedirectResponse
    {
        Gate::authorize('delete', $credit);

        $credit->delete();

        return back()->with('success', 'Crédit supprimé.');
    }
}
