<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PointingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_session_lists_only_unpointed_operations_oldest_first(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->for($account)->create(['label' => 'Pointée', 'pointed_at' => now()]);
        Transaction::factory()->for($account)->create(['label' => 'Récente', 'pointed_at' => null, 'occurred_on' => '2026-08-10']);
        Transaction::factory()->for($account)->create(['label' => 'Ancienne', 'pointed_at' => null, 'occurred_on' => '2026-08-02']);

        $this->actingAs($account->user)
            ->get("/compte/{$account->id}/pointage")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Pointing/Session')
                ->has('transactions', 2)
                ->where('transactions.0.label', 'Ancienne')
                ->where('transactions.1.label', 'Récente'));
    }

    public function test_someone_else_s_account_is_off_limits(): void
    {
        $account = Account::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/compte/{$account->id}/pointage")
            ->assertForbidden();
    }

    public function test_an_operation_added_with_the_stay_flag_comes_back_to_the_session(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($account->user)
            ->from("/compte/{$account->id}/pointage")
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => 'depense',
                'amount' => '8,20',
                'label' => 'Péage oublié',
                'stay' => true,
            ])
            ->assertRedirect("/compte/{$account->id}/pointage");

        $this->assertSame('Péage oublié', $account->transactions()->sole()->label);
    }

    public function test_push_subscriptions_can_be_stored_and_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/notifications/abonnement', [
                'endpoint' => 'https://push.example/abc',
                'keys' => ['p256dh' => 'clef-p256dh', 'auth' => 'clef-auth'],
            ])
            ->assertRedirect();

        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)
            ->delete('/notifications/abonnement', ['endpoint' => 'https://push.example/abc'])
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->pushSubscriptions()->count());
    }
}
