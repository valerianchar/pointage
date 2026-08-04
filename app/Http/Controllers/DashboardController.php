<?php

namespace App\Http\Controllers;

use App\Queries\BalanceHistory;
use App\Queries\MonthlyTotals;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MonthlyTotals $monthlyTotals,
        private readonly BalanceHistory $balanceHistory,
    ) {}

    /**
     * Accueil : patrimoine, flux du mois et évolution du solde.
     *
     * La liste des comptes vient des propriétés partagées — la sidebar en a besoin
     * partout —, et le patrimoine total s'en déduit côté front.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = CarbonImmutable::now();
        $totals = $this->monthlyTotals->forUser($user, $month);

        return Inertia::render('Home', [
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'income_cents' => $totals['income_cents'],
            'expense_cents' => $totals['expense_cents'],
            'balance_history' => $this->balanceHistory->weeklyForUser($user, $month),
        ]);
    }
}
