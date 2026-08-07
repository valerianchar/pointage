<?php

namespace Tests\Feature;

use App\Actions\GenerateRecurringTransactions;
use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_counts_what_is_left_to_point_this_month(): void
    {
        // Milieu de mois : il reste de la place pour une échéance « à venir ».
        $this->travelTo(now()->startOfMonth()->addDays(14));

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $tag = Tag::factory()->for($account)->named('Abonnements')->create();
        $template = RecurringTransaction::factory()->for($account)->create();

        Transaction::factory()->for($account)->for($tag)->for($template)->expense(7_340)->pointed()->create();
        Transaction::factory()->for($account)->for($tag)->for($template)->expense(1_099)
            ->on(now()->startOfMonth()->addDay()->toDateString())
            ->create();

        // Posée d'avance : présentée « à venir », ni à pointer ni comptée.
        Transaction::factory()->for($account)->for($tag)->for($template)->expense(2_000)
            ->on(now()->setDay(20)->toDateString())
            ->create();

        // Une opération ponctuelle ne doit pas apparaître sur cet écran.
        Transaction::factory()->for($account)->expense(4_250)->create();

        $this->actingAs($user)
            ->get('/recurrentes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Recurring/Index')
                ->has('instances', 2)
                ->where('pending_count', 1)
                ->where('total_count', 2)
                ->where('instances.0.account_name', $account->name)
                ->where('instances.0.tag', 'Abonnements')
                ->has('upcoming', 1)
                ->where('upcoming.0.amount_cents', -2_000)
                ->where('upcoming.0.day_label', 'le 20'));
    }

    public function test_instances_from_other_months_are_left_out(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $template = RecurringTransaction::factory()->for($account)->create();

        Transaction::factory()->for($account)->for($template)
            ->on(now()->startOfMonth()->subMonth()->toDateString())
            ->create();

        $this->actingAs($user)
            ->get('/recurrentes')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('instances', 0)->where('total_count', 0));
    }

    public function test_generation_creates_one_unpointed_instance_per_active_template(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->onDay(1)->create(['amount_cents' => -82_000]);
        RecurringTransaction::factory()->for($account)->onDay(5)->create(['amount_cents' => 240_000]);
        RecurringTransaction::factory()->for($account)->inactive()->create();

        $createdCount = (new GenerateRecurringTransactions)->handle(CarbonImmutable::parse('2026-09-15'));

        $this->assertSame(2, $createdCount);
        $this->assertSame(2, $account->transactions()->count());
        $this->assertSame(0, $account->transactions()->whereNotNull('pointed_at')->count());
        $this->assertSame(
            ['2026-09-01', '2026-09-05'],
            $account->transactions()->orderBy('occurred_on')->get()
                ->map(fn (Transaction $instance): string => $instance->occurred_on->toDateString())->all(),
        );
    }

    public function test_generation_can_be_replayed_without_creating_duplicates(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->create();

        $generate = new GenerateRecurringTransactions;
        $month = CarbonImmutable::parse('2026-09-15');

        $this->assertSame(1, $generate->handle($month));
        $this->assertSame(0, $generate->handle($month));
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_a_template_set_on_the_thirty_first_falls_on_the_last_day_of_short_months(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->onDay(31)->create();

        (new GenerateRecurringTransactions)->handle(CarbonImmutable::parse('2026-02-28'));

        $this->assertSame('2026-02-28', $account->transactions()->sole()->occurred_on->toDateString());
    }

    public function test_an_instance_is_posted_ahead_of_its_day_and_dated_it(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->onDay(15)->create();

        $generate = new GenerateRecurringTransactions;

        // Le passage du 1er pose déjà l'échéance du 15, « à venir »…
        $this->assertSame(1, $generate->handle(CarbonImmutable::parse('2026-09-01')));
        $this->assertSame('2026-09-15', $account->transactions()->sole()->occurred_on->toDateString());

        // …et le jour venu, rien à recréer.
        $this->assertSame(0, $generate->handle(CarbonImmutable::parse('2026-09-15')));
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_a_missed_day_is_caught_up_by_the_next_run(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->onDay(3)->create();

        // Le planificateur n'a pas tourné du 3 au 7 : le passage du 8 rattrape.
        $this->assertSame(1, (new GenerateRecurringTransactions)->handle(CarbonImmutable::parse('2026-09-08')));
        $this->assertSame('2026-09-03', $account->transactions()->sole()->occurred_on->toDateString());
    }

    public function test_generated_instances_change_the_balance(): void
    {
        $account = Account::factory()->startingAt(100_000)->create();
        RecurringTransaction::factory()->for($account)->create(['amount_cents' => -82_000]);

        (new GenerateRecurringTransactions)->handle(CarbonImmutable::parse('2026-09-15'));

        $this->assertSame(18_000, $account->fresh()->balance_cents);
    }

    public function test_the_command_generates_the_month_and_rejects_a_malformed_one(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->create();

        $this->artisan('transactions:generate-recurring', ['--month' => '2026-09'])->assertSuccessful();
        $this->assertSame('2026-09-01', $account->transactions()->sole()->occurred_on->toDateString());

        $this->artisan('transactions:generate-recurring', ['--month' => 'septembre'])->assertFailed();
    }

    public function test_the_command_defaults_to_the_current_month(): void
    {
        $account = Account::factory()->create();
        RecurringTransaction::factory()->for($account)->create();

        $this->artisan('transactions:generate-recurring')->assertSuccessful();

        $this->assertSame(
            now()->startOfMonth()->toDateString(),
            $account->transactions()->sole()->occurred_on->toDateString(),
        );
    }
}
