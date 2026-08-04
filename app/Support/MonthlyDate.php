<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Échéances mensuelles réglées sur un jour du mois.
 *
 * Un prélèvement fixé au 31 tombe le dernier jour des mois plus courts : février
 * n'a pas de 31, et l'échéance ne doit pas glisser au mois suivant.
 */
final class MonthlyDate
{
    public static function inMonth(CarbonInterface $month, int $dayOfMonth): CarbonImmutable
    {
        $startOfMonth = CarbonImmutable::instance($month)->startOfMonth();

        return $startOfMonth->setDay(min($dayOfMonth, $startOfMonth->daysInMonth));
    }

    /**
     * Première échéance qui tombe à la date donnée ou après.
     */
    public static function onOrAfter(CarbonInterface $from, int $dayOfMonth): CarbonImmutable
    {
        $thisMonth = self::inMonth($from, $dayOfMonth);

        return $thisMonth->lessThan(CarbonImmutable::instance($from)->startOfDay())
            ? self::inMonth(CarbonImmutable::instance($from)->addMonthNoOverflow(), $dayOfMonth)
            : $thisMonth;
    }
}
