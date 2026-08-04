<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_preference_travels_with_every_page(): void
    {
        $user = User::factory()->create(['hide_balances' => true]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.hide_balances', true));
    }

    public function test_balances_can_be_hidden_then_shown_again(): void
    {
        $user = User::factory()->create(['hide_balances' => false]);

        $this->actingAs($user)->patch('/confidentialite', ['hide_balances' => true])->assertRedirect();
        $this->assertTrue($user->fresh()->hide_balances);

        $this->patch('/confidentialite', ['hide_balances' => false]);
        $this->assertFalse($user->fresh()->hide_balances);
    }

    public function test_the_preference_requires_a_boolean(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/confidentialite', ['hide_balances' => 'peut-être'])
            ->assertSessionHasErrors('hide_balances');
    }
}
