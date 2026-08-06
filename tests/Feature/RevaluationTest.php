<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\MonthlyTotals;
use App\Queries\PointedActivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_market_drop_becomes_a_pointed_negative_adjustment(): void
    {
        $account = Account::factory()->startingAt(1_248_030)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/reevaluation", ['current_value' => '12 100,00'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Compte réévalué.');

        $revaluation = $account->transactions()->sole();
        $this->assertSame(-38_030, $revaluation->amount_cents);
        $this->assertTrue($revaluation->is_revaluation);
        $this->assertTrue($revaluation->isPointed());
        $this->assertSame(1_210_000, $account->fresh()->balance_cents);
    }

    public function test_a_market_rise_becomes_a_positive_adjustment(): void
    {
        $account = Account::factory()->startingAt(100_000)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/reevaluation", ['current_value' => '1 050'])
            ->assertRedirect();

        $this->assertSame(5_000, $account->transactions()->sole()->amount_cents);
        $this->assertSame(105_000, $account->fresh()->balance_cents);
    }

    public function test_an_already_correct_balance_creates_nothing(): void
    {
        $account = Account::factory()->startingAt(100_000)->create();

        $this->actingAs($account->user)
            ->post("/compte/{$account->id}/reevaluation", ['current_value' => '1 000,00'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Le solde correspond déjà : rien à réévaluer.');

        $this->assertSame(0, $account->transactions()->count());
    }

    public function test_revaluations_stay_out_of_the_monthly_flows(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->for($account)->create(['amount_cents' => -5_000, 'occurred_on' => now()]);
        Transaction::factory()->for($account)->create([
            'amount_cents' => -38_030,
            'occurred_on' => now(),
            'is_revaluation' => true,
            'pointed_at' => now(),
        ]);

        $totals = (new MonthlyTotals)->forUser($account->user, CarbonImmutable::now());

        $this->assertSame(5_000, $totals['expense_cents']);
        $this->assertSame(0, $totals['income_cents']);
    }

    public function test_revaluations_stay_out_of_the_pointed_activity_but_count_as_pointed(): void
    {
        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);
        Transaction::factory()->for($account)->create([
            'amount_cents' => -5_000,
            'occurred_on' => now(),
            'pointed_at' => now(),
        ]);
        Transaction::factory()->for($account)->create([
            'amount_cents' => 12_000,
            'occurred_on' => now(),
            'is_revaluation' => true,
            'pointed_at' => now(),
        ]);

        $activity = (new PointedActivity)->forUser($account->user, CarbonImmutable::now());

        $this->assertSame(5_000, $activity['expenses_cents']);
        $this->assertSame(0, $activity['incomes_cents']);
        $this->assertSame(2, $activity['pointed_count']);

        $closureTotals = (new PointedActivity)->totalsForAccount($account, CarbonImmutable::now());
        $this->assertSame(5_000, $closureTotals['expenses_cents']);
        $this->assertSame(0, $closureTotals['incomes_cents']);
    }

    public function test_someone_else_s_account_cannot_be_revalued(): void
    {
        $account = Account::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/compte/{$account->id}/reevaluation", ['current_value' => '1'])
            ->assertForbidden();

        $this->assertSame(0, $account->transactions()->count());
    }
}
