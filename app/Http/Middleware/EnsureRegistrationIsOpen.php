<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pointage.registration_open')) {
            return redirect()
                ->route('login')
                ->with('error', 'Les inscriptions sont fermées sur cette instance.');
        }

        return $next($request);
    }
}
