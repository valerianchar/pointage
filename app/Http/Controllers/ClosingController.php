<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClosingRequest;
use App\Http\Resources\AccountClosingResource;
use App\Http\Resources\TransactionResource;
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
            /*
             * La bannière de rappel et l'écran « Pointage obligatoire » arrivent
             * ici avec le compte à clôturer : le panneau s'ouvre directement.
             */
            'closing_account_id' => $request->integer('cloture') ?: null,
            'closing_open' => $request->boolean('pointer'),
            'closing_transactions' => $this->closingTransactions($request, $today),
        ]);
    }

    /**
     * Opérations à pointer dans le panneau de clôture : celles de la période
     * courante du compte demandé, plus les non pointées restées des périodes
     * précédentes — comme sur la page du compte.
     *
     * @return list<array<string, mixed>>
     */
    private function closingTransactions(Request $request, CarbonImmutable $today): array
    {
        $account = $request->user()->accounts()
            ->when(
                $request->integer('cloture'),
                fn ($query, int $accountId) => $query->where('accounts.id', $accountId),
            )
            ->orderBy('id')
            ->first();

        if ($account === null) {
            return [];
        }

        $period = $account->pointingPeriod($today);

        $transactions = $account->transactions()
            ->where(fn ($query) => $query
                ->whereBetween('occurred_on', [$period->start->toDateString(), $period->end->toDateString()])
                ->orWhereNull('pointed_at'))
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        return TransactionResource::collection($transactions)->resolve();
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

        /*
         * La période suivante démarre le lendemain de la fin clôturée : les
         * fenêtres s'enchaînent sans trou ni chevauchement, comme sur la maquette.
         */
        $account->update([
            'period_start_day' => $account->period_end_day >= 31 ? 1 : $account->period_end_day + 1,
        ]);

        return back()->with('success', 'Mois clôturé.');
    }
}
