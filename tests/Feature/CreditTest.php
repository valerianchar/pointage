<?php

namespace Tests\Feature;

use App\Actions\GenerateRecurringTransactions;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Credit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_lists_the_credits_with_their_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Compte principal']);
        Credit::factory()->for($account)->named('Prêt auto')->of(1_420_000, 639_000, 23_650)->create();

        $this->actingAs($user)
            ->get('/credits')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Credits/Index')
                ->has('credits', 1)
                ->where('credits.0.name', 'Prêt auto')
                ->where('credits.0.account_name', 'Compte principal')
                ->where('credits.0.remaining_cents', 639_000)
                ->where('credits.0.monthly_cents', 23_650)
                // (14 200 − 6 390) / 14 200
                ->where('credits.0.repaid_percent', 55));
    }

    public function test_another_profile_credits_are_never_listed(): void
    {
        Credit::factory()->for(Account::factory()->for(User::factory()->create()))->create();

        $this->actingAs(User::factory()->create())
            ->get('/credits')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('credits', 0));
    }

    public function test_a_credit_can_be_declared_with_amounts_written_the_french_way(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt immobilier',
                'borrowed' => '180 000',
                'remaining' => '142 300,00',
                'monthly' => '745,00 €',
                'term_months' => 240,
                'payment_day' => 10,
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame('Prêt immobilier', $credit->name);
        $this->assertSame(18_000_000, $credit->borrowed_cents);
        $this->assertSame(14_230_000, $credit->remaining_cents);
        $this->assertSame(74_500, $credit->monthly_cents);
        $this->assertSame(21, $credit->repaid_percent);
    }

    public function test_declaring_a_credit_puts_the_month_instalment_on_the_account(): void
    {
        $this->travelTo('2026-08-10');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->startingAt(100_000)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt auto',
                'borrowed' => '12 000',
                'remaining' => '9 000',
                'monthly' => '250',
                'term_months' => 36,
                'payment_day' => 20,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Le modèle récurrent naît avec le crédit…
        $template = $account->recurringTransactions()->sole();
        $this->assertSame(-25_000, $template->amount_cents);
        $this->assertSame(20, $template->day_of_month);
        $this->assertSame($account->credits()->sole()->id, $template->credit_id);

        // …et l'échéance du mois est déjà sur le compte, « à venir », comptée dans le solde.
        $instalment = $account->transactions()->sole();
        $this->assertSame('2026-08-20', $instalment->occurred_on->toDateString());
        $this->assertNull($instalment->pointed_at);
        $this->assertSame(100_000 - 25_000, $account->fresh()->balance_cents);

        // La génération quotidienne ne la recrée pas : l'index mensuel est déjà pris.
        $created = app(GenerateRecurringTransactions::class)->handle(CarbonImmutable::parse('2026-08-20'));
        $this->assertSame(0, $created);
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_a_member_declaring_a_credit_on_a_joint_account_puts_the_instalment_too(): void
    {
        $this->travelTo('2026-08-07');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $account = Account::factory()->for($owner)->ofType(AccountType::Joint)->startingAt(100_000)->create();
        AccountMember::factory()->accepted()->create(['account_id' => $account->id, 'user_id' => $member->id]);

        $this->actingAs($member)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt immobilier',
                'remaining' => '150 000',
                'monthly' => '250',
                'term_months' => 240,
                'payment_day' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // L'échéance du 10 est posée, « à venir »…
        $instalment = $account->transactions()->sole();
        $this->assertSame('2026-08-10', $instalment->occurred_on->toDateString());
        $this->assertNull($instalment->pointed_at);

        // …et l'écran du compte la sépare du solde du jour dans sa projection.
        $this->actingAs($member)
            ->get("/compte/{$account->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('balance_today_cents', 100_000)
                ->where('projected_balance_cents', 100_000 - 25_000));
    }

    public function test_deleting_the_credit_stops_the_instalment(): void
    {
        $this->travelTo('2026-08-10');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post('/credits', [
            'account_id' => $account->id,
            'name' => 'Prêt auto',
            'remaining' => '9 000',
            'monthly' => '250',
            'term_months' => 36,
            'payment_day' => 20,
        ]);

        $credit = $account->credits()->sole();

        $this->actingAs($user)
            ->delete("/credits/{$credit->id}")
            ->assertRedirect();

        // L'échéance à venir — pure projection — s'efface, le modèle s'éteint.
        $this->assertSame(0, $account->transactions()->count());
        $this->assertFalse($account->recurringTransactions()->sole()->is_active);
        $this->assertSame(0, $account->credits()->count());
    }

    public function test_giving_only_the_remaining_capital_starts_the_credit_at_nothing_repaid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt travaux',
                'remaining' => '9 000',
                'monthly' => '150',
                'term_months' => 60,
                'payment_day' => 5,
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame(900_000, $credit->borrowed_cents);
        $this->assertSame(900_000, $credit->remaining_cents);
        $this->assertSame(0, $credit->repaid_percent);
    }

    public function test_giving_only_the_borrowed_capital_assumes_nothing_repaid_yet(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt étudiant',
                'borrowed' => '12 000',
                'monthly' => '100',
                'term_months' => 120,
                'payment_day' => 15,
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame(1_200_000, $credit->borrowed_cents);
        $this->assertSame(1_200_000, $credit->remaining_cents);
    }

    public function test_a_credit_without_any_capital_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', ['account_id' => $account->id, 'name' => 'Prêt', 'monthly' => '150'])
            ->assertSessionHasErrors(['borrowed', 'remaining']);

        $this->assertSame(0, $account->credits()->count());
    }

    public function test_a_remaining_capital_above_the_borrowed_one_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt',
                'borrowed' => '10 000',
                'remaining' => '12 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors('remaining');

        $this->assertSame(0, $account->credits()->count());
    }

    public function test_a_credit_without_a_monthly_payment_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', ['account_id' => $account->id, 'name' => 'Prêt', 'borrowed' => '10 000'])
            ->assertSessionHasErrors('monthly');
    }

    public function test_a_nameless_credit_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => '',
                'borrowed' => '10 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_declaring_a_credit_on_another_profile_account_is_refused(): void
    {
        $foreignAccount = Account::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->post('/credits', [
                'account_id' => $foreignAccount->id,
                'name' => 'Prêt',
                'borrowed' => '10 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertSame(0, $foreignAccount->credits()->count());
    }

    public function test_a_credit_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $credit = Credit::factory()->for(Account::factory()->for($user))->create();

        $this->actingAs($user)
            ->delete("/credits/{$credit->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('credits', ['id' => $credit->id]);
    }

    public function test_deleting_another_profile_credit_is_refused(): void
    {
        $credit = Credit::factory()->for(Account::factory()->for(User::factory()->create()))->create();

        $this->actingAs(User::factory()->create())
            ->delete("/credits/{$credit->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('credits', ['id' => $credit->id]);
    }

    public function test_a_fully_repaid_credit_never_passes_a_hundred_percent(): void
    {
        $credit = Credit::factory()->of(1_000_000, 0, 10_000)->create();

        $this->assertSame(100, $credit->repaid_percent);
    }

    public function test_the_account_screen_shows_only_that_account_credits(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();

        Credit::factory()->for($account)->named('Prêt auto')->create();
        Credit::factory()->for($otherAccount)->named('Prêt immobilier')->create();

        $this->actingAs($user)
            ->get("/compte/{$account->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('credits', 1)
                ->where('credits.0.name', 'Prêt auto'));
    }

    public function test_it_records_the_term_and_the_debit_day(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt immobilier',
                'borrowed' => '180 000',
                'remaining' => '142 300',
                'monthly' => '745',
                'term_months' => 240,
                'payment_day' => 10,
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame(240, $credit->term_months);
        $this->assertSame(10, $credit->payment_day);
        $this->assertSame('20 ans', $credit->term_label);
    }

    public function test_a_credit_without_a_term_or_a_debit_day_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt',
                'borrowed' => '10 000',
                'monthly' => '150',
            ])
            ->assertSessionHasErrors(['term_months', 'payment_day']);

        $this->assertSame(0, $account->credits()->count());
    }

    public function test_an_impossible_debit_day_or_term_is_refused(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt',
                'borrowed' => '10 000',
                'monthly' => '150',
                'term_months' => 700,
                'payment_day' => 32,
            ])
            ->assertSessionHasErrors(['term_months', 'payment_day']);
    }

    #[DataProvider('terms')]
    public function test_the_term_reads_in_years_and_months(int $termMonths, string $expectedLabel): void
    {
        $credit = Credit::factory()->over($termMonths, 5)->create();

        $this->assertSame($expectedLabel, $credit->term_label);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function terms(): array
    {
        return [
            'cinq ans' => [60, '5 ans'],
            'un an' => [12, '1 an'],
            'huit mois' => [8, '8 mois'],
            'un an et demi' => [18, '1 an et 6 mois'],
            'vingt ans et trois mois' => [243, '20 ans et 3 mois'],
        ];
    }

    public function test_the_next_payment_is_this_month_when_the_day_is_still_ahead(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-04 09:00'));

        $credit = Credit::factory()->over(60, 5)->create();

        $this->assertSame('2026-08-05', $credit->next_payment_on->toDateString());
    }

    public function test_the_next_payment_rolls_to_the_following_month_once_the_day_has_passed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 09:00'));

        $credit = Credit::factory()->over(60, 5)->create();

        $this->assertSame('2026-09-05', $credit->next_payment_on->toDateString());
    }

    public function test_a_debit_day_beyond_the_month_length_falls_on_its_last_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-02-01 09:00'));

        $credit = Credit::factory()->over(60, 31)->create();

        $this->assertSame('2026-02-28', $credit->next_payment_on->toDateString());
    }

    public function test_it_estimates_the_instalments_left_at_the_current_pace(): void
    {
        $credit = Credit::factory()->of(1_420_000, 639_000, 23_650)->create();

        // 6 390 / 236,50 = 27,02 : la dernière mensualité compte.
        $this->assertSame(28, $credit->remaining_instalments);
    }

    public function test_a_credit_declared_before_the_schedule_fields_still_shows(): void
    {
        $user = User::factory()->create();
        Credit::factory()->for(Account::factory()->for($user))->withoutSchedule()->create();

        $this->actingAs($user)
            ->get('/credits')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('credits.0.term_label', null)
                ->where('credits.0.payment_day', null)
                ->where('credits.0.next_payment_label', null)
                ->etc());
    }

    public function test_a_term_that_is_not_a_whole_number_of_years_can_be_declared(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/credits', [
                'account_id' => $account->id,
                'name' => 'Prêt électroménager',
                'borrowed' => '1 800',
                'monthly' => '100',
                'term_months' => 18,
                'payment_day' => 12,
            ])
            ->assertRedirect();

        $credit = $account->credits()->sole();

        $this->assertSame(18, $credit->term_months);
        $this->assertSame('1 an et 6 mois', $credit->term_label);
    }
}
