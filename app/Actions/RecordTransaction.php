<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class RecordTransaction
{
    /**
     * Enregistre une opération non pointée. Cochée « récurrente », elle crée aussi
     * le modèle qui la fera réapparaître les mois suivants, et l'opération du mois
     * devient la première instance de ce modèle.
     */
    public function handle(
        Account $account,
        string $label,
        int $signedAmountCents,
        ?Tag $tag,
        bool $isRecurring,
        CarbonInterface $occurredOn,
    ): Transaction {
        return DB::transaction(function () use ($account, $label, $signedAmountCents, $tag, $isRecurring, $occurredOn): Transaction {
            $template = $isRecurring
                ? $account->recurringTransactions()->create([
                    'label' => $label,
                    'amount_cents' => $signedAmountCents,
                    'day_of_month' => $occurredOn->day,
                    'tag_id' => $tag?->id,
                ])
                : null;

            return $account->transactions()->create([
                'label' => $label,
                'amount_cents' => $signedAmountCents,
                'tag_id' => $tag?->id,
                'recurring_transaction_id' => $template?->id,
                'occurred_on' => $occurredOn->toDateString(),
                'pointed_at' => null,
            ]);
        });
    }
}
