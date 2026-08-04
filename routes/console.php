<?php

use App\Console\Commands\GenerateRecurringTransactionsCommand;
use Illuminate\Support\Facades\Schedule;

/*
 * Les opérations récurrentes du mois apparaissent le 1er, non pointées, comme
 * l'annonce l'écran « Récurrentes ». La commande est idempotente : un rattrapage
 * manuel ne crée pas de doublon.
 */
Schedule::command(GenerateRecurringTransactionsCommand::class)
    ->monthlyOn(1, '00:05')
    ->withoutOverlapping();
