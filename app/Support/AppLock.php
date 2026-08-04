<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * Verrou de confidentialité de la session.
 *
 * Le cadenas de la maquette mobile ne déconnecte pas : il masque l'application
 * derrière un écran de déverrouillage tout en gardant la session ouverte.
 */
final class AppLock
{
    private const SESSION_KEY = 'app.locked';

    public static function engage(): void
    {
        Session::put(self::SESSION_KEY, true);
    }

    public static function release(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function isEngaged(): bool
    {
        return (bool) Session::get(self::SESSION_KEY, false);
    }
}
