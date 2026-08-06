<?php

namespace Tests\Feature;

use App\Events\AccountActivityChanged;
use App\Events\AccountDeletionDecided;
use App\Events\AccountDeletionRequested;
use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Supprimer un compte partagé n'est pas un geste unilatéral : chaque membre
 * peut le demander — fini le 403 —, les autres tranchent, l'unanimité
 * l'emporte et un seul refus l'annule.
 */
class AccountDeletionConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_now_ask_for_deletion_instead_of_a_403(): void
    {
        Event::fake([AccountDeletionRequested::class]);

        $account = Account::factory()->create();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        $this->actingAs($member->user)
            ->delete("/compte/{$account->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        // Le compte est toujours là : seule la demande est ouverte, déjà approuvée par le demandeur.
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $request = $account->deletionRequest()->sole();
        $this->assertSame($member->user_id, $request->requested_by);
        $this->assertTrue($request->hasApprovalFrom($member->user));

        // Les autres votants — ici le propriétaire — sont prévenus en temps réel.
        Event::assertDispatched(AccountDeletionRequested::class, fn (AccountDeletionRequested $event): bool => $event->recipientIds === [$account->user_id]);
    }

    public function test_unanimity_deletes_and_a_lone_account_needs_nobody(): void
    {
        Event::fake([AccountDeletionDecided::class]);

        $account = Account::factory()->create();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        // Le membre demande, le propriétaire accepte : unanimité, le compte part.
        $this->actingAs($member->user)->delete("/compte/{$account->id}");
        $request = $account->deletionRequest()->sole();

        $this->actingAs($account->user)
            ->post("/suppressions/{$request->id}/accepter")
            ->assertRedirect('/');

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        Event::assertDispatched(AccountDeletionDecided::class, fn (AccountDeletionDecided $event): bool => $event->deleted);

        // Un compte sans autre membre, lui, se supprime d'un geste.
        $lone = Account::factory()->create();
        $this->actingAs($lone->user)->delete("/compte/{$lone->id}")->assertRedirect('/');
        $this->assertDatabaseMissing('accounts', ['id' => $lone->id]);
    }

    public function test_a_single_refusal_cancels_the_request(): void
    {
        Event::fake([AccountDeletionDecided::class]);

        $account = Account::factory()->create();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        $this->actingAs($member->user)->delete("/compte/{$account->id}");
        $request = $account->deletionRequest()->sole();

        $this->actingAs($account->user)
            ->delete("/suppressions/{$request->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertDatabaseCount('account_deletion_requests', 0);
        $this->assertDatabaseCount('account_deletion_approvals', 0);
        Event::assertDispatched(AccountDeletionDecided::class, fn (AccountDeletionDecided $event): bool => ! $event->deleted);
    }

    public function test_a_stranger_has_no_say(): void
    {
        $account = Account::factory()->create();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        $this->actingAs($member->user)->delete("/compte/{$account->id}");
        $request = $account->deletionRequest()->sole();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post("/suppressions/{$request->id}/accepter")->assertForbidden();
        $this->actingAs($stranger)->delete("/suppressions/{$request->id}")->assertForbidden();
        $this->actingAs($stranger)->delete("/compte/{$account->id}")->assertForbidden();
    }

    public function test_the_pending_request_waits_on_the_other_voters_home(): void
    {
        $account = Account::factory()->create();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        $this->actingAs($member->user)->delete("/compte/{$account->id}");

        $this->actingAs($account->user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('deletion_requests', 1)
                ->where('deletion_requests.0.account_name', $account->name)
                ->where('deletion_requests.0.requester_name', $member->user->name));

        // Le demandeur, lui, a déjà voté : rien à trancher sur son accueil.
        $this->actingAs($member->user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('deletion_requests', 0));
    }

    public function test_shared_account_activity_notifies_the_other_members(): void
    {
        Event::fake([AccountActivityChanged::class]);

        $account = Account::factory()->create();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        Transaction::factory()->for($account)->expense(1_000)->create();

        // Créée hors session (générations nocturnes) : tout le monde est prévenu.
        Event::assertDispatched(AccountActivityChanged::class, fn (AccountActivityChanged $event): bool => collect($event->recipientIds)->sort()->values()->all()
            === collect([$account->user_id, $member->user_id])->sort()->values()->all());
    }
}
