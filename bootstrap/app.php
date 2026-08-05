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
         * Aucun proxy de confiance n'est déclaré : en production, le serveur qui
         * termine le TLS est l'application elle-même. Faire confiance aux en-têtes
         * X-Forwarded-* permettrait de falsifier l'adresse IP, donc de contourner la
         * limite de tentatives de connexion. Un CDN placé devant plus tard devra
         * être déclaré ici, nommément.
         */
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(new RetryExpiredSession);
    })->create();
