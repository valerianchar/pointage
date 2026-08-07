<?php

namespace App\Actions;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\MonthlyDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class GenerateRecurringTransactions
{
    /**
     * Crée l'instance non pointée de chaque modèle actif pour le mois de la date
     * de référence, datée de son jour — y compris quand ce jour n'est pas encore
     * arrivé. Une échéance connue d'avance se voit d'avance : elle apparaît « à
     * venir » sur le compte, et la projection n'a rien à deviner. Le premier
     * passage du mois pose donc tout le mois d'un coup.
     *
     * L'opération est idempotente : relancer la génération sur un mois déjà
     * traité ne crée aucun doublon, ce que garantit aussi l'index unique
     * (recurring_transaction_id, occurred_on).
     *
     * @return int Nombre d'opérations créées
     */
    public function handle(CarbonInterface $referenceDate): int
    {
        $month = $referenceDate;

        $templates = RecurringTransaction::query()
            ->where('is_active', true)
            ->get(['id', 'account_id', 'tag_id', 'label', 'amount_cents', 'day_of_month']);

        if ($templates->isEmpty()) {
            return 0;
        }

        $alreadyGenerated = $this->templateIdsGeneratedFor($month, $templates);

        $missingInstances = $templates
            ->reject(fn (RecurringTransaction $template): bool => $alreadyGenerated->contains($template->id))
            ->map(fn (RecurringTransaction $template): array => [
                'account_id' => $template->account_id,
                'tag_id' => $template->tag_id,
                'recurring_transaction_id' => $template->id,
                'label' => $template->label,
                'amount_cents' => $template->amount_cents,
                'occurred_on' => $this->occurrenceDate($month, $template->day_of_month),
                'pointed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values();

        if ($missingInstances->isEmpty()) {
            return 0;
        }

        Transaction::insert($missingInstances->all());

        return $missingInstances->count();
    }

    /**
     * @param  Collection<int, RecurringTransaction>  $templates
     * @return Collection<int, int>
     */
    private function templateIdsGeneratedFor(CarbonInterface $month, Collection $templates): Collection
    {
        return Transaction::query()
            ->whereIn('recurring_transaction_id', $templates->pluck('id'))
            ->whereBetween('occurred_on', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->pluck('recurring_transaction_id');
    }

    private function occurrenceDate(CarbonInterface $month, int $dayOfMonth): string
    {
        return MonthlyDate::inMonth($month, $dayOfMonth)->toDateString();
    }
}
