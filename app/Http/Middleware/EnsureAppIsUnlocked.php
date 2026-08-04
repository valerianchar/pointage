<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppIsUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AppLock::isEngaged()) {
            return redirect()->route('lock.show');
        }

        return $next($request);
    }
}
