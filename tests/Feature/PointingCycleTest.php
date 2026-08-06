<?php

namespace Tests\Feature;

use App\Actions\SendPointingReminders;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Une opération « à venir » — datée après aujourd'hui — pèse sur le solde mais
 * n'entre dans le cycle de pointage qu'à sa date : pas de reste à pointer, pas
 * de rappel, pas de file de clôture pour elle.
 */
class PointingCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_upcoming_operation_stays_out_of_the_pending_counts(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->startingAt(100_000)->create();

        Transaction::factory()->for($account)->expense(2_000)->on(now()->toDateString())->create();
        Transaction::factory()->for($account)->expense(35_000)->on(now()->addMonthNoOverflow()->toDateString())->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Le solde anticipe la dépense différée…
                ->where('accounts.0.balance_cents', 100_000 - 2_000 - 35_000)
                // …mais elle n'est pas encore à pointer.
                ->where('accounts.0.pending_count', 1));
    }

    public function test_the_closing_panel_leaves_upcoming_operations_aside(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)
            ->create(['period_start_day' => 1, 'period_end_day' => 31]);

        Transaction::factory()->for($account)->expense(2_000)->on(now()->toDateString())->create();
        Transaction::factory()->for($account)->expense(9_000)->on(now()->addMonthNoOverflow()->toDateString())->create();

        $this->actingAs($user)
            ->get("/bilan?cloture={$account->id}&pointer=1")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('closing_transactions', 1)
                ->where('activity.pending_count', 1));
    }

    public function test_no_reminder_fires_when_only_upcoming_operations_remain(): void
    {
        Notification::fake();

        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);
        $account->user->updatePushSubscription('https://push.example/1', 'p256dh', 'auth');

        // Différée au mois suivant : rien à rapprocher du relevé d'août.
        Transaction::factory()->for($account)
            ->on('2026-09-03')
            ->create(['pointed_at' => null]);

        $sent = (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-31'));

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
    }
}
