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
     * Enregistre une opération non pointée. Cochée « récurrente », elle crée aussi
     * le modèle qui la fera réapparaître les mois suivants, au jour choisi.
     *
     * Si ce jour est déjà passé — ou est aujourd'hui —, l'opération du mois est
     * créée tout de suite, datée de ce jour, et devient la première instance du
     * modèle. Un jour encore à venir ne crée rien : la génération quotidienne
     * s'en chargera à la date dite.
     */
    public function handle(
        Account $account,
        string $label,
        int $signedAmountCents,
        ?Tag $tag,
        bool $isRecurring,
        CarbonInterface $today,
        ?int $recurringDay = null,
    ): ?Transaction {
        return DB::transaction(function () use ($account, $label, $signedAmountCents, $tag, $isRecurring, $today, $recurringDay): ?Transaction {
            $dayOfMonth = $recurringDay ?? $today->day;
            $occurrence = $isRecurring ? MonthlyDate::inMonth($today, $dayOfMonth) : $today;

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
                'pointed_at' => null,
            ]);
        });
    }
}
