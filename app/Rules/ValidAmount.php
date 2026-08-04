<?php

namespace App\Rules;

use App\Support\Amount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide un montant saisi à la française (virgule ou point, espaces de milliers).
 */
final class ValidAmount implements ValidationRule
{
    public function __construct(private readonly int $minimumCents = 0) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cents = Amount::toCents(is_scalar($value) ? (string) $value : null);

        if ($cents === null) {
            $fail('Saisissez un montant valide, par exemple 64,90.');

            return;
        }

        if (abs($cents) < $this->minimumCents) {
            $fail('Le montant doit être supérieur à zéro.');
        }
    }
}
