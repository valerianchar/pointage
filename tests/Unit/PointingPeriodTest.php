<?php

namespace Tests\Unit;

use App\Support\PointingPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PointingPeriodTest extends TestCase
{
    /**
     * @return array<string, array{string, int, int, string, string}>
     */
    public static function periods(): array
    {
        return [
            'mois civil' => ['2026-08-15', 1, 31, '2026-08-01', '2026-08-31'],
            'fin de mois plus courte que le jour demandé' => ['2026-02-10', 1, 31, '2026-02-01', '2026-02-28'],
            'cycle à cheval, après le jour de début' => ['2026-08-15', 5, 4, '2026-08-05', '2026-09-04'],
            'cycle à cheval, avant le jour de début' => ['2026-08-03', 5, 4, '2026-07-05', '2026-08-04'],
            'cycle à cheval, le jour de début' => ['2026-08-05', 5, 4, '2026-08-05', '2026-09-04'],
        ];
    }

    #[DataProvider('periods')]
    public function test_the_window_containing_a_date_follows_the_account_days(
        string $date,
        int $startDay,
        int $endDay,
        string $expectedStart,
        string $expectedEnd,
    ): void {
        $period = PointingPeriod::containing(CarbonImmutable::parse($date), $startDay, $endDay);

        $this->assertSame($expectedStart, $period->start->toDateString());
        $this->assertSame($expectedEnd, $period->end->toDateString());
    }

    public function test_a_date_inside_the_window_is_contained(): void
    {
        $period = PointingPeriod::containing(CarbonImmutable::parse('2026-08-15'), 5, 4);

        $this->assertTrue($period->contains(CarbonImmutable::parse('2026-09-04')));
        $this->assertTrue($period->contains(CarbonImmutable::parse('2026-08-05')));
        $this->assertFalse($period->contains(CarbonImmutable::parse('2026-08-04')));
        $this->assertFalse($period->contains(CarbonImmutable::parse('2026-09-05')));
    }

    public function test_the_label_reads_like_the_mockup(): void
    {
        $period = PointingPeriod::containing(CarbonImmutable::parse('2026-08-15'), 5, 4);

        $this->assertSame('du 5 au 4', $period->label());
    }
}
