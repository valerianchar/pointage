<?php

namespace App\Support;

/**
 * Conversion des montants saisis à la française vers des centiers entiers.
 *
 * Les écrans acceptent « 1 234,56 », « 1.234,56 », « 1234.56 » ou « 64,90 € » :
 * toutes ces écritures désignent le même montant.
 */
final class Amount
{
    private const SEPARATORS_TO_STRIP = [' ', "\u{00A0}", "\u{202F}", '€'];

    /**
     * Renvoie le montant en centimes, ou null si la saisie n'est pas un nombre.
     */
    public static function toCents(?string $rawAmount): ?int
    {
        $stripped = str_replace(self::SEPARATORS_TO_STRIP, '', (string) $rawAmount);

        if ($stripped === '') {
            return null;
        }

        $decimal = self::normalizeSeparators($stripped);

        if (! is_numeric($decimal)) {
            return null;
        }

        return (int) round(((float) $decimal) * 100);
    }

    /**
     * Quand les deux séparateurs sont présents, le point sépare les milliers et
     * la virgule les décimales — l'écriture française « 1.234,56 ».
     */
    private static function normalizeSeparators(string $stripped): string
    {
        $hasComma = str_contains($stripped, ',');
        $hasDot = str_contains($stripped, '.');

        if ($hasComma && $hasDot) {
            return str_replace(',', '.', str_replace('.', '', $stripped));
        }

        return str_replace(',', '.', $stripped);
    }
}
