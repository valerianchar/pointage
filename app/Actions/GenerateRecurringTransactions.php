<?php

namespace App\Actions;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\MonthlyDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class GenerateRecurringTransactions
{
    /**
     * Crée l'instance non pointée de chaque modèle actif dont le jour du mois est
     * arrivé — la génération tourne chaque jour, et chaque récurrente apparaît à
     * sa date plutôt que toutes ensemble le 1er.
     *
     * L'opération est idempotente : relancer la génération sur un jour déjà traité
     * ne crée aucun doublon, ce que garantit aussi l'index unique
     * (recurring_transaction_id, occurred_on). Elle rattrape aussi les jours
     * manqués : tout ce qui était dû avant la date de référence est créé.
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
            ->reject(fn (RecurringTransaction $template): bool => $this->isStillToCome($referenceDate, $template))
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

    /**
     * Vrai quand le jour du modèle n'est pas encore arrivé dans le mois de la
     * date de référence : l'instance sera créée par un passage ultérieur.
     */
    private function isStillToCome(CarbonInterface $referenceDate, RecurringTransaction $template): bool
    {
        return MonthlyDate::inMonth($referenceDate, $template->day_of_month)
            ->greaterThan(CarbonImmutable::instance($referenceDate)->endOfDay());
    }

    private function occurrenceDate(CarbonInterface $month, int $dayOfMonth): string
    {
        return MonthlyDate::inMonth($month, $dayOfMonth)->toDateString();
    }
}
