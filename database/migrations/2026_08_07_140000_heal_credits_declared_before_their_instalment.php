<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Les crédits déclarés avant que la déclaration ne pose sa mensualité sur le
 * compte n'ont ni modèle récurrent ni échéance : leur prélèvement n'apparaît
 * jamais dans les « à venir » et la projection du compte l'ignore. Rien ne les
 * rattrapait — la génération quotidienne ne travaille qu'à partir des modèles.
 *
 * Chaque crédit orphelin reçoit ici ce que sa déclaration lui aurait donné.
 * Si le compte porte déjà un modèle récurrent au même montant et au même jour
 * — une mensualité suivie à la main avant la fonctionnalité — c'est lui qui
 * est adopté plutôt que doublé.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = CarbonImmutable::now();

        $orphanCredits = DB::table('credits')
            ->whereNotNull('payment_day')
            ->where('monthly_cents', '>', 0)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('recurring_transactions')
                ->whereColumn('recurring_transactions.credit_id', 'credits.id'))
            ->get();

        foreach ($orphanCredits as $credit) {
            $templateId = $this->adoptManualTemplate($credit) ?? $this->createTemplate($credit, $now);

            if (! $this->hasInstanceInMonth($templateId, $now)) {
                $this->createMonthInstalment($credit, $templateId, $now);
            }
        }
    }

    /**
     * Un modèle actif du même compte, au même montant et au même jour, sans
     * crédit attaché : la mensualité que quelqu'un suivait déjà à la main.
     * Le rattacher évite un doublon qui débiterait deux fois chaque mois.
     */
    private function adoptManualTemplate(object $credit): ?int
    {
        $templateId = DB::table('recurring_transactions')
            ->where('account_id', $credit->account_id)
            ->where('is_active', true)
            ->whereNull('credit_id')
            ->where('amount_cents', -$credit->monthly_cents)
            ->where('day_of_month', $credit->payment_day)
            ->value('id');

        if ($templateId === null) {
            return null;
        }

        DB::table('recurring_transactions')
            ->where('id', $templateId)
            ->update(['credit_id' => $credit->id, 'updated_at' => CarbonImmutable::now()]);

        return (int) $templateId;
    }

    private function createTemplate(object $credit, CarbonImmutable $now): int
    {
        return (int) DB::table('recurring_transactions')->insertGetId([
            'account_id' => $credit->account_id,
            'label' => 'Mensualité — '.$credit->name,
            'amount_cents' => -$credit->monthly_cents,
            'day_of_month' => $credit->payment_day,
            'credit_id' => $credit->id,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function hasInstanceInMonth(int $templateId, CarbonImmutable $month): bool
    {
        return DB::table('transactions')
            ->where('recurring_transaction_id', $templateId)
            ->whereBetween('occurred_on', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->exists();
    }

    /**
     * L'échéance du mois en cours, comme la déclaration l'aurait posée :
     * « à venir » si le jour n'est pas passé, à pointer sinon. Un jour au-delà
     * de la longueur du mois tombe sur son dernier jour.
     */
    private function createMonthInstalment(object $credit, int $templateId, CarbonImmutable $now): void
    {
        $day = min((int) $credit->payment_day, $now->daysInMonth);

        DB::table('transactions')->insert([
            'account_id' => $credit->account_id,
            'label' => 'Mensualité — '.$credit->name,
            'amount_cents' => -$credit->monthly_cents,
            'recurring_transaction_id' => $templateId,
            'occurred_on' => $now->startOfMonth()->setDay($day)->toDateString(),
            'pointed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
