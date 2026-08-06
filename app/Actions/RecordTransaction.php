<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Support\MonthlyDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class RecordTransaction
{
    /**
     * Enregistre une opération, non pointée par défaut. Cochée « récurrente »,
     * elle crée aussi le modèle qui la fera réapparaître les mois suivants, au
     * jour choisi.
     *
     * Si ce jour est déjà passé — ou est aujourd'hui —, l'opération du mois est
     * créée tout de suite, datée de ce jour, et devient la première instance du
     * modèle. Un jour encore à venir ne crée rien : la génération quotidienne
     * s'en chargera à la date dite.
     *
     * `$alreadyPointed` sert au panneau de clôture : un oubli repéré sur le
     * relevé y figure déjà — il naît pointé.
     *
     * `$occurredOn` date une opération ponctuelle — passée pour un rattrapage,
     * future pour une dépense différée : elle pèse sur le solde dès maintenant
     * et attendra son relevé pour être pointée. Une récurrente l'ignore, son
     * jour du mois fait foi.
     */
    public function handle(
        Account $account,
        string $label,
        int $signedAmountCents,
        ?Tag $tag,
        bool $isRecurring,
        CarbonInterface $today,
        ?int $recurringDay = null,
        bool $alreadyPointed = false,
        ?CarbonInterface $occurredOn = null,
    ): ?Transaction {
        return DB::transaction(function () use ($account, $label, $signedAmountCents, $tag, $isRecurring, $today, $recurringDay, $alreadyPointed, $occurredOn): ?Transaction {
            $dayOfMonth = $recurringDay ?? $today->day;
            $occurrence = $isRecurring ? MonthlyDate::inMonth($today, $dayOfMonth) : ($occurredOn ?? $today);

            $template = $isRecurring
                ? $account->recurringTransactions()->create([
                    'label' => $label,
                    'amount_cents' => $signedAmountCents,
                    'day_of_month' => $dayOfMonth,
                    'tag_id' => $tag?->id,
                ])
                : null;

            if ($isRecurring && $occurrence->greaterThan(CarbonImmutable::instance($today)->endOfDay())) {
                return null;
            }

            return $account->transactions()->create([
                'label' => $label,
                'amount_cents' => $signedAmountCents,
                'tag_id' => $tag?->id,
                'recurring_transaction_id' => $template?->id,
                'occurred_on' => $occurrence->toDateString(),
                'pointed_at' => $alreadyPointed ? $today : null,
            ]);
        });
    }
}
