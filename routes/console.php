<?php

use App\Console\Commands\GenerateRecurringTransactionsCommand;
use App\Console\Commands\SendPointingRemindersCommand;
use App\Console\Commands\SyncCryptoValuationsCommand;
use Illuminate\Support\Facades\Schedule;

/*
 * Chaque opération récurrente apparaît le jour choisi, non pointée. Le passage
 * est quotidien et idempotent : un rattrapage manuel ne crée pas de doublon, et
 * un jour manqué est rattrapé au passage suivant.
 */
Schedule::command(GenerateRecurringTransactionsCommand::class)
    ->dailyAt('00:05')
    ->withoutOverlapping();

/*
 * Le rappel part en matinée, quand le relevé de la veille est consultable —
 * une notification nocturne serait perdue dans la pile du réveil.
 */
Schedule::command(SendPointingRemindersCommand::class)
    ->dailyAt('08:00')
    ->withoutOverlapping();

/*
 * Les comptes à positions crypto se recalent au cours du jour avant que la
 * journée ne commence. En cas de panne de l'API, les cours de la veille
 * restent en place et le passage suivant rattrape.
 */
Schedule::command(SyncCryptoValuationsCommand::class)
    ->dailyAt('05:30')
    ->withoutOverlapping();
