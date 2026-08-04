<?php

namespace App\Console\Commands;

use App\Actions\GenerateRecurringTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRecurringTransactionsCommand extends Command
{
    protected $signature = 'transactions:generate-recurring
                            {--month= : Mois à générer au format AAAA-MM (mois courant par défaut)}';

    protected $description = 'Crée les opérations récurrentes du mois, en attente de pointage';

    public function handle(GenerateRecurringTransactions $generateRecurringTransactions): int
    {
        $month = $this->resolveMonth();

        if ($month === null) {
            $this->components->error('Le mois doit être au format AAAA-MM, par exemple 2026-08.');

            return self::FAILURE;
        }

        $createdCount = $generateRecurringTransactions->handle($month);

        $this->components->info(
            "{$createdCount} opération(s) récurrente(s) créée(s) pour {$month->translatedFormat('F Y')}."
        );

        return self::SUCCESS;
    }

    private function resolveMonth(): ?CarbonImmutable
    {
        $requestedMonth = $this->option('month');

        if ($requestedMonth === null) {
            return CarbonImmutable::now()->startOfMonth();
        }

        if (preg_match('/^\d{4}-\d{2}$/', $requestedMonth) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $requestedMonth.'-01')->startOfMonth();
    }
}
