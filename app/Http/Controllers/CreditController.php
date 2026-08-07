<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditRequest;
use App\Http\Resources\CreditResource;
use App\Models\Account;
use App\Models\Credit;
use App\Support\MonthlyDate;
use Carbon\CarbonImmutable;
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
            ->whereIn('account_id', $request->user()->accessibleAccounts()->select('accounts.id'))
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

        $credit = $account->credits()->create([
            'name' => $request->string('name')->trim()->value(),
            'borrowed_cents' => $request->borrowedCents(),
            'remaining_cents' => $request->remainingCents(),
            'monthly_cents' => $request->monthlyCents(),
            'term_months' => $request->integer('term_months'),
            'payment_day' => $request->integer('payment_day'),
        ]);

        /*
         * La mensualité est une récurrente comme une autre : son modèle naît
         * avec le crédit, et l'échéance du mois apparaît tout de suite sur le
         * compte — « à venir » si le jour n'est pas passé, à pointer sinon.
         */
        if ($credit->payment_day !== null && $credit->monthly_cents > 0) {
            $template = $account->recurringTransactions()->create([
                'label' => 'Mensualité — '.$credit->name,
                'amount_cents' => -$credit->monthly_cents,
                'day_of_month' => $credit->payment_day,
                'credit_id' => $credit->id,
            ]);

            $account->transactions()->create([
                'label' => $template->label,
                'amount_cents' => $template->amount_cents,
                'recurring_transaction_id' => $template->id,
                'occurred_on' => MonthlyDate::inMonth(CarbonImmutable::now(), $credit->payment_day)->toDateString(),
                'pointed_at' => null,
            ]);
        }

        return back()->with('success', 'Crédit déclaré — sa mensualité du mois est sur le compte.');
    }

    public function destroy(Credit $credit): RedirectResponse
    {
        Gate::authorize('delete', $credit);

        /*
         * Le modèle de mensualité s'éteint avec le crédit : plus de génération,
         * et les échéances encore à venir — pures projections — s'effacent.
         * L'historique passé, lui, reste.
         */
        $template = $credit->recurringTransaction;

        if ($template !== null) {
            $template->transactions()
                ->whereNull('pointed_at')
                ->where('occurred_on', '>', now()->toDateString())
                ->delete();
            $template->update(['is_active' => false, 'credit_id' => null]);
        }

        $credit->delete();

        return back()->with('success', 'Crédit supprimé — sa mensualité ne se recréera plus.');
    }
}
