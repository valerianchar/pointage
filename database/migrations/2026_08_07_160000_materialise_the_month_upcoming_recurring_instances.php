<?php

use App\Actions\GenerateRecurringTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;

/*
 * La génération pose désormais le mois entier d'avance : chaque récurrente
 * apparaît « à venir » sur son compte dès le début du mois, au lieu d'attendre
 * son jour. Sans ce passage, les échéances restantes du mois en cours
 * n'apparaîtraient qu'au prochain réveil du planificateur — celui-ci les pose
 * tout de suite. Idempotent, comme la génération qu'il appelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(GenerateRecurringTransactions::class)->handle(CarbonImmutable::now());
    }
};
