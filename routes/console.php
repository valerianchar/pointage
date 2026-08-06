<?php

use App\Console\Commands\GenerateRecurringTransactionsCommand;
use Illuminate\Support\Facades\Schedule;

/*
 * Chaque opération récurrente apparaît le jour choisi, non pointée. Le passage
 * est quotidien et idempotent : un rattrapage manuel ne crée pas de doublon, et
 * un jour manqué est rattrapé au passage suivant.
 */
Schedule::command(GenerateRecurringTransactionsCommand::class)
    ->dailyAt('00:05')
    ->withoutOverlapping();
