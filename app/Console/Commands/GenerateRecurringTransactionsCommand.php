<?php

namespace App\Console\Commands;

use App\Actions\GenerateRecurringTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRecurringTransactionsCommand extends Command
{
    protected $signature = 'transactions:generate-recurring
                            {--month= : Mois à rattraper en entier, au format AAAA-MM (par défaut, les échéances dues à ce jour)}';

    protected $description = 'Crée les opérations récurrentes arrivées à échéance, en attente de pointage';

    public function handle(GenerateRecurringTransactions $generateRecurringTransactions): int
    {
        $referenceDate = $this->resolveReferenceDate();

        if ($referenceDate === null) {
            $this->components->error('Le mois doit être au format AAAA-MM, par exemple 2026-08.');

            return self::FAILURE;
        }

        $createdCount = $generateRecurringTransactions->handle($referenceDate);

        $this->components->info(
            "{$createdCount} opération(s) récurrente(s) créée(s) pour {$referenceDate->translatedFormat('F Y')}."
        );

        return self::SUCCESS;
    }

    /**
     * Sans option, les échéances dues aujourd'hui ; avec `--month`, la fin du mois
     * demandé — tout le mois est alors rattrapé d'un coup.
     */
    private function resolveReferenceDate(): ?CarbonImmutable
    {
        $requestedMonth = $this->option('month');

        if ($requestedMonth === null) {
            return CarbonImmutable::now();
        }

        if (preg_match('/^\d{4}-\d{2}$/', $requestedMonth) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $requestedMonth.'-01')->endOfMonth();
    }
}
