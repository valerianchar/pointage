<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_renders(): void
    {
        $this->get('/inscription')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Register'));
    }

    public function test_a_profile_can_be_created_and_is_signed_in(): void
    {
        $this->post('/inscription', [
            'name' => 'Marie Olivier',
            'email' => 'marie@exemple.fr',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'marie@exemple.fr', 'name' => 'Marie Olivier']);
    }

    public function test_an_already_used_email_is_refused(): void
    {
        $existing = User::factory()->create();

        $this->post('/inscription', [
            'name' => 'Quelqu’un',
            'email' => $existing->email,
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_two_passwords_must_match(): void
    {
        $this->post('/inscription', [
            'name' => 'Marie Olivier',
            'email' => 'marie@exemple.fr',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'autre-chose',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}
