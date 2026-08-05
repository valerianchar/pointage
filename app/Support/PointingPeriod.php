<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Fenêtre de pointage courante d'un compte, réglée sur ses jours de début et de fin.
 *
 * « Du 1 au 31 » suit le mois civil. « Du 5 au 4 » désigne un cycle à cheval sur
 * deux mois : la fenêtre contenant la date donnée démarre le 5 le plus récent.
 */
final class PointingPeriod
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function containing(CarbonInterface $date, int $startDay, int $endDay): self
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        if ($startDay <= $endDay) {
            return new self(
                MonthlyDate::inMonth($day, $startDay),
                MonthlyDate::inMonth($day, $endDay),
            );
        }

        $start = $day->day >= $startDay
            ? MonthlyDate::inMonth($day, $startDay)
            : MonthlyDate::inMonth($day->subMonthNoOverflow(), $startDay);

        return new self($start, MonthlyDate::inMonth($start->addMonthNoOverflow(), $endDay));
    }

    public function contains(CarbonInterface $date): bool
    {
        return CarbonImmutable::instance($date)->betweenIncluded($this->start, $this->end);
    }

    /**
     * « du 1 au 31 », tel qu'affiché sous les champs de la page Bilan.
     */
    public function label(): string
    {
        return 'du '.$this->start->day.' au '.$this->end->day;
    }
}
