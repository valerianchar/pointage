<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointingPeriodUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pointing_period_defaults_to_the_calendar_month(): void
    {
        // Le défaut vient de la base : le modèle doit être relu pour le voir.
        $account = Account::factory()->create()->refresh();

        $this->assertSame(1, $account->period_start_day);
        $this->assertSame(31, $account->period_end_day);
    }

    public function test_the_owner_can_change_the_pointing_period(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch("/compte/{$account->id}/periode", [
                'period_start_day' => 5,
                'period_end_day' => 4,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(5, $account->period_start_day);
        $this->assertSame(4, $account->period_end_day);
    }

    public function test_days_outside_the_month_are_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch("/compte/{$account->id}/periode", [
                'period_start_day' => 0,
                'period_end_day' => 32,
            ])
            ->assertSessionHasErrors(['period_start_day', 'period_end_day']);
    }

    public function test_another_profile_cannot_change_the_period(): void
    {
        $account = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->patch("/compte/{$account->id}/periode", [
                'period_start_day' => 5,
                'period_end_day' => 4,
            ])
            ->assertForbidden();
    }
}
