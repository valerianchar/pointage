<?php

namespace Tests\Unit;

use App\Support\Amount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountTest extends TestCase
{
    #[DataProvider('acceptedWritings')]
    public function test_it_reads_amounts_written_the_french_way(string $input, int $expectedCents): void
    {
        $this->assertSame($expectedCents, Amount::toCents($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function acceptedWritings(): array
    {
        return [
            'virgule décimale' => ['64,90', 6_490],
            'point décimal' => ['64.90', 6_490],
            'espace de milliers' => ['12 480,30', 1_248_030],
            'espace insécable' => ["12\u{00A0}480,30", 1_248_030],
            'point de milliers et virgule décimale' => ['1.234,56', 123_456],
            'symbole euro' => ['64,90 €', 6_490],
            'entier' => ['200', 20_000],
            'négatif' => ['-820', -82_000],
            'arrondi au centime' => ['0,015', 2],
        ];
    }

    #[DataProvider('rejectedWritings')]
    public function test_it_rejects_what_is_not_a_number(?string $input): void
    {
        $this->assertNull(Amount::toCents($input));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function rejectedWritings(): array
    {
        return [
            'vide' => [''],
            'nul' => [null],
            'texte' => ['abc'],
            'deux virgules' => ['1,2,3'],
            'symbole seul' => ['€'],
        ];
    }
}
