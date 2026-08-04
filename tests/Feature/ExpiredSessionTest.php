<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExpiredSessionTest extends TestCase
{
    /**
     * Le middleware CSRF ne s'applique pas pendant les tests : on provoque donc
     * l'exception qu'il lèverait sur un jeton périmé.
     */
    private function routeWithAnExpiredToken(): string
    {
        Route::post('/jeton-perime', fn () => throw new TokenMismatchException)->middleware('web');

        return '/jeton-perime';
    }

    public function test_an_inertia_visit_is_sent_back_with_a_message_it_can_show(): void
    {
        $route = $this->routeWithAnExpiredToken();

        $this->from('/connexion')
            ->post($route, [], ['X-Inertia' => 'true'])
            // 303 : le code qu'Inertia attend pour suivre une redirection.
            ->assertStatus(303)
            ->assertRedirect('/connexion')
            ->assertSessionHas('error', 'Votre session avait expiré. Réessayez, la page est à jour.');
    }

    public function test_a_plain_request_keeps_the_standard_page_expired_response(): void
    {
        $route = $this->routeWithAnExpiredToken();

        $this->post($route)->assertStatus(419);
    }
}
