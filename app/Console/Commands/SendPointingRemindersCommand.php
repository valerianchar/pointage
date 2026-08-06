<?php

namespace App\Console\Commands;

use App\Actions\SendPointingReminders;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendPointingRemindersCommand extends Command
{
    protected $signature = 'pointage:send-reminders';

    protected $description = 'Envoie les rappels de fin de période de pointage aux navigateurs abonnés';

    public function handle(SendPointingReminders $sendPointingReminders): int
    {
        $sentCount = $sendPointingReminders->handle(CarbonImmutable::now());

        $this->components->info("{$sentCount} rappel(s) de pointage envoyé(s).");

        return self::SUCCESS;
    }
}
