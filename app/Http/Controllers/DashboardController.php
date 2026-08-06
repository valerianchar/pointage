<?php

namespace App\Http\Controllers;

use App\Enums\DashboardWidget;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\BalanceHistory;
use App\Queries\CreditTotals;
use App\Queries\MonthlyPointing;
use App\Queries\MonthlyTotals;
use App\Queries\RecurringInstances;
use App\Queries\TagSpending;
use App\Queries\TopExpenses;
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
        private readonly MonthlyPointing $monthlyPointing,
        private readonly RecurringInstances $recurringInstances,
        private readonly CreditTotals $creditTotals,
        private readonly TagSpending $tagSpending,
        private readonly TopExpenses $topExpenses,
    ) {}

    /**
     * Accueil : la même sélection de widgets partout — grille sur desktop,
     * pile sous le patrimoine sur mobile.
     *
     * La liste des comptes vient des propriétés partagées — la sidebar en a besoin
     * partout — d'où sont déduits côté front le patrimoine et la répartition.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = CarbonImmutable::now();
        $totals = $this->monthlyTotals->forUser($user, $month);
        $enabledWidgets = $user->enabledDashboardWidgets();

        return Inertia::render('Home', [
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            /*
             * Flux du mois et évolution du solde restent envoyés quels que soient
             * les widgets choisis : leurs widgets n'ont pas de calcul dédié.
             */
            'income_cents' => $totals['income_cents'],
            'expense_cents' => $totals['expense_cents'],
            'balance_history' => $this->balanceHistory->weeklyForUser($user, $month),
            'widgets' => DashboardWidget::describe($enabledWidgets),
            ...$this->widgetData($user, $month, $totals['expense_cents'], $enabledWidgets),
        ]);
    }

    /**
     * Chaque widget desktop n'est calculé que s'il est affiché.
     *
     * @param  list<string>  $enabledWidgets
     * @return array<string, mixed>
     */
    private function widgetData(User $user, CarbonImmutable $month, int $expenseCents, array $enabledWidgets): array
    {
        $data = [];

        if ($this->shows(DashboardWidget::Pending, $enabledWidgets)) {
            $data['pointing'] = $this->monthlyPointing->forUserMonth($user, $month);
        }

        if ($this->shows(DashboardWidget::DailyAverage, $enabledWidgets)) {
            $data['daily_expense'] = $this->dailyExpense($month, $expenseCents);
        }

        if ($this->shows(DashboardWidget::RecurringCharge, $enabledWidgets)) {
            $data['recurring_charge'] = $this->recurringCharge($user, $month);
        }

        if ($this->shows(DashboardWidget::Credits, $enabledWidgets)) {
            $data['credit_totals'] = $this->creditTotals->forUser($user);
        }

        if ($this->shows(DashboardWidget::TagSpending, $enabledWidgets)) {
            $data['tag_spending'] = $this->tagSpending->forUserMonth($user, $month);
        }

        if ($this->shows(DashboardWidget::TopExpenses, $enabledWidgets)) {
            $data['top_expenses'] = $this->topExpenses->forUserMonth($user, $month);
        }

        return $data;
    }

    /**
     * @param  list<string>  $enabledWidgets
     */
    private function shows(DashboardWidget $widget, array $enabledWidgets): bool
    {
        return in_array($widget->value, $enabledWidgets, true);
    }

    /**
     * Dépense moyenne par jour écoulé, et projection à ce rythme sur tout le mois.
     *
     * @return array{average_cents: int, projected_cents: int}
     */
    private function dailyExpense(CarbonImmutable $month, int $expenseCents): array
    {
        $daysElapsed = $month->day;
        $averageCents = (int) round($expenseCents / $daysElapsed);

        return [
            'average_cents' => $averageCents,
            'projected_cents' => $averageCents * $month->daysInMonth,
        ];
    }

    /**
     * @return array{charge_cents: int, pending_count: int, total_count: int}
     */
    private function recurringCharge(User $user, CarbonImmutable $month): array
    {
        $instances = $this->recurringInstances->forUserMonth($user, $month);

        return [
            // Seules les récurrentes qui sortent de l'argent forment une charge.
            'charge_cents' => $instances
                ->filter(fn (Transaction $instance): bool => $instance->amount_cents < 0)
                ->sum(fn (Transaction $instance): int => abs($instance->amount_cents)),
            'pending_count' => $instances->reject(fn (Transaction $instance): bool => $instance->isPointed())->count(),
            'total_count' => $instances->count(),
        ];
    }
}
