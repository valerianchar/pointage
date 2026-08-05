<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClosingRequest;
use App\Http\Resources\AccountClosingResource;
use App\Models\Account;
use App\Models\AccountClosing;
use App\Queries\PointedActivity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClosingController extends Controller
{
    public function __construct(private readonly PointedActivity $pointedActivity) {}

    public function index(Request $request): Response
    {
        $today = CarbonImmutable::now();

        $closings = AccountClosing::query()
            ->whereIn('account_id', $request->user()->accounts()->select('accounts.id'))
            ->with('account')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Closings/Index', [
            'month_label' => Str::ucfirst($today->translatedFormat('F Y')),
            'activity' => $this->pointedActivity->forUser($request->user(), $today),
            'closings' => AccountClosingResource::collection($closings)->resolve(),
        ]);
    }

    public function store(StoreClosingRequest $request): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        Gate::authorize('update', $account);

        $today = CarbonImmutable::now();
        $period = $account->pointingPeriod($today);
        $totals = $this->pointedActivity->totalsForAccount($account, $today);

        $account->closings()->create([
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'theoretical_balance_cents' => $account->balance_cents,
            'real_balance_cents' => $request->realBalanceCents(),
            'pointed_expenses_cents' => $totals['expenses_cents'],
            'pointed_incomes_cents' => $totals['incomes_cents'],
            'note' => $request->string('note')->trim()->value() ?: null,
        ]);

        return back()->with('success', 'Mois clôturé.');
    }
}
