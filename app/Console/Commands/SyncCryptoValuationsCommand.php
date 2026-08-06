<?php

namespace App\Console\Commands;

use App\Actions\SyncCryptoValuations;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncCryptoValuationsCommand extends Command
{
    protected $signature = 'pointage:sync-crypto';

    protected $description = 'Rafraîchit les cours crypto et recale les comptes à positions sur leur valeur de marché';

    public function handle(SyncCryptoValuations $syncCryptoValuations): int
    {
        $revaluedCount = $syncCryptoValuations->handle(CarbonImmutable::now());

        $this->components->info("{$revaluedCount} compte(s) réévalué(s) au cours du jour.");

        return self::SUCCESS;
    }
}
