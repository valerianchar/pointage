<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AppLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_locking_hides_the_application_behind_the_lock_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/verrouillage')->assertRedirect('/verrouillage');

        $this->get('/')->assertRedirect('/verrouillage');
        $this->get('/tags')->assertRedirect('/verrouillage');

        // La session reste ouverte : verrouiller n'est pas se déconnecter.
        $this->assertAuthenticated();
    }

    public function test_the_lock_screen_renders_while_locked(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/verrouillage');

        $this->get('/verrouillage')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Lock'));
    }

    public function test_the_right_password_lifts_the_lock(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/verrouillage');

        $this->delete('/verrouillage', ['password' => 'password'])->assertRedirect('/');

        $this->get('/')->assertOk();
    }

    public function test_a_wrong_password_keeps_the_lock(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/verrouillage');

        $this->delete('/verrouillage', ['password' => 'pas-le-bon'])->assertSessionHasErrors('password');

        $this->get('/')->assertRedirect('/verrouillage');
    }

    public function test_a_device_confirmation_lifts_the_lock(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/verrouillage');

        $this->post('/verrouillage/biometrie')->assertRedirect('/');

        $this->get('/')->assertOk();
    }

    public function test_the_lock_screen_redirects_home_when_nothing_is_locked(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/verrouillage')
            ->assertRedirect('/');
    }
}
