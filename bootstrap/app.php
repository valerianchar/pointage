<?php

use App\Exceptions\RetryExpiredSession;
use App\Http\Middleware\EnsureAppIsUnlocked;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'unlocked' => EnsureAppIsUnlocked::class,
            'registration' => EnsureRegistrationIsOpen::class,
        ]);

        /*
         * Le déclencheur de tâches est appelé par un service de cron externe, qui
         * n'a évidemment pas de jeton de session à présenter. Il s'authentifie par
         * son propre jeton porteur.
         */
        $middleware->validateCsrfTokens(except: ['taches/recurrentes']);

        /*
         * Sur le VPS, le serveur qui termine le TLS est l'application elle-même :
         * aucun proxy n'est déclaré par défaut. Faire confiance aux en-têtes
         * X-Forwarded-* permettrait sinon de falsifier l'adresse IP, donc de
         * contourner la limite de tentatives de connexion.
         *
         * Sur un hébergeur qui place son propre proxy devant (Render…), c'est
         * l'inverse : sans TRUSTED_PROXIES, tous les visiteurs partagent l'adresse
         * IP du proxy — le blocage de connexion devient global, et un inconnu peut
         * verrouiller l'e-mail du propriétaire à distance. Renseignez alors « * »,
         * ou une liste d'adresses séparées par des virgules.
         */
        $trustedProxies = env('TRUSTED_PROXIES');

        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(new RetryExpiredSession);
    })->create();
