<?php

namespace App\Console\Commands;

use App\Actions\SyncPositionValuations;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncPositionValuationsCommand extends Command
{
    protected $signature = 'pointage:sync-positions';

    protected $description = 'Rafraîchit les cours — crypto et ETF — et recale les comptes à positions sur leur valeur de marché';

    public function handle(SyncPositionValuations $syncPositionValuations): int
    {
        $revaluedCount = $syncPositionValuations->handle(CarbonImmutable::now());

        $this->components->info("{$revaluedCount} compte(s) réévalué(s) au cours du jour.");

        return self::SUCCESS;
    }
}
