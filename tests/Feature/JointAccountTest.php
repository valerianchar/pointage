<?php

namespace Tests\Feature;

use App\Actions\DeleteAccount;
use App\Actions\SendPointingReminders;
use App\Enums\AccountType;
use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PointingPeriodEnded;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class JointAccountTest extends TestCase
{
    use RefreshDatabase;

    private function jointAccount(): Account
    {
        return Account::factory()->ofType(AccountType::Joint)->create();
    }

    public function test_the_owner_invites_by_exact_email_and_no_access_opens_before_acceptance(): void
    {
        $account = $this->jointAccount();
        $invitee = User::factory()->create(['email' => 'conjoint@exemple.fr']);

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/membres", ['email' => 'conjoint@exemple.fr'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $member = $account->members()->sole();
        $this->assertSame($invitee->id, $member->user_id);
        $this->assertFalse($member->isAccepted());

        // Invité mais pas encore membre : la porte reste fermée.
        $this->actingAs($invitee)->get("/compte/{$account->id}")->assertForbidden();
        $this->assertFalse($invitee->accessibleAccounts()->whereKey($account->id)->exists());
    }

    public function test_the_invitation_waits_on_the_home_and_acceptance_opens_the_account(): void
    {
        $account = $this->jointAccount();
        $member = AccountMember::factory()->for($account)->create();
        $invitee = $member->user;

        $this->actingAs($invitee)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invitations', 1)
                ->where('invitations.0.account_name', $account->name));

        $this->actingAs($invitee)
            ->post("/membres/{$member->id}/accepter")
            ->assertRedirect();

        $this->assertTrue($member->fresh()->isAccepted());
        $this->actingAs($invitee)->get("/compte/{$account->id}")->assertOk();

        // Le compte joint apparaît désormais dans ses comptes, avec les siens.
        $this->assertTrue($invitee->accessibleAccounts()->whereKey($account->id)->exists());
    }

    public function test_an_accepted_member_records_operations_like_at_home(): void
    {
        $account = $this->jointAccount();
        $member = AccountMember::factory()->for($account)->accepted()->create();

        $this->actingAs($member->user)
            ->post('/operations', [
                'account_id' => $account->id,
                'direction' => TransactionDirection::Expense->value,
                'amount' => '48,30',
                'label' => 'Courses communes',
                'is_recurring' => false,
            ])
            ->assertRedirect("/compte/{$account->id}");

        $this->assertSame(-4_830, $account->transactions()->sole()->amount_cents);
    }

    public function test_only_the_owner_invites_and_nobody_invites_twice_or_himself(): void
    {
        $account = $this->jointAccount();
        $member = AccountMember::factory()->for($account)->accepted()->create();
        $other = User::factory()->create(['email' => 'ami@exemple.fr']);

        // Un membre n'a pas les clés : il ne peut pas inviter.
        $this->actingAs($member->user)
            ->post("/compte/{$account->id}/membres", ['email' => 'ami@exemple.fr'])
            ->assertForbidden();

        // E-mail inconnu, auto-invitation, doublon : trois refus expliqués.
        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/membres", ['email' => 'personne@exemple.fr'])
            ->assertSessionHasErrors('email');
        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/membres", ['email' => $account->user->email])
            ->assertSessionHasErrors('email');
        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/membres", ['email' => $member->user->email])
            ->assertSessionHasErrors('email');
    }

    public function test_inviting_works_only_on_joint_accounts(): void
    {
        $account = Account::factory()->ofType(AccountType::Current)->create();
        User::factory()->create(['email' => 'ami@exemple.fr']);

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/membres", ['email' => 'ami@exemple.fr'])
            ->assertNotFound();
    }

    public function test_declining_leaving_and_removing_all_close_the_door(): void
    {
        $account = $this->jointAccount();

        // L'invité refuse.
        $invitation = AccountMember::factory()->for($account)->create();
        $this->actingAs($invitation->user)->delete("/membres/{$invitation->id}")->assertRedirect();
        $this->assertDatabaseMissing('account_members', ['id' => $invitation->id]);

        // Le membre quitte.
        $leaving = AccountMember::factory()->for($account)->accepted()->create();
        $this->actingAs($leaving->user)->delete("/membres/{$leaving->id}")->assertRedirect();
        $this->assertDatabaseMissing('account_members', ['id' => $leaving->id]);

        // Le propriétaire retire ; un tiers, lui, ne peut rien.
        $removed = AccountMember::factory()->for($account)->accepted()->create();
        $this->actingAs(User::factory()->create())->delete("/membres/{$removed->id}")->assertForbidden();
        $this->actingAs($account->user)->delete("/membres/{$removed->id}")->assertRedirect();
        $this->assertDatabaseMissing('account_members', ['id' => $removed->id]);

        $ousted = $removed->user->fresh();
        $this->actingAs($ousted)->get("/compte/{$account->id}")->assertForbidden();
    }

    public function test_deleting_the_account_purges_its_members(): void
    {
        $account = $this->jointAccount();
        AccountMember::factory()->for($account)->accepted()->create();

        (new DeleteAccount)->handle($account);

        $this->assertSame(0, AccountMember::query()->count());
    }

    public function test_the_pointing_reminder_reaches_every_member(): void
    {
        Notification::fake();

        $account = Account::factory()
            ->ofType(AccountType::Joint)
            ->create(['period_start_day' => 1, 'period_end_day' => 31]);
        $member = AccountMember::factory()->for($account)->accepted()->create();

        $account->user->updatePushSubscription('https://push.example/proprietaire', 'p256dh', 'auth');
        $member->user->updatePushSubscription('https://push.example/membre', 'p256dh', 'auth');
        Transaction::factory()->for($account)->create(['pointed_at' => null]);

        $sent = (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-31'));

        $this->assertSame(2, $sent);
        Notification::assertSentTo($account->user, PointingPeriodEnded::class);
        Notification::assertSentTo($member->user, PointingPeriodEnded::class);
    }
}
