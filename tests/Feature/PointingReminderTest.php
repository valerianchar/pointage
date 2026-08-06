<?php

namespace Tests\Feature;

use App\Actions\SendPointingReminders;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PointingPeriodEnded;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PointingReminderTest extends TestCase
{
    use RefreshDatabase;

    private function subscribe(User $user): void
    {
        $user->updatePushSubscription('https://push.example/'.$user->id, 'clef-p256dh', 'clef-auth');
    }

    public function test_a_reminder_goes_out_when_the_period_ends_with_pending_operations(): void
    {
        Notification::fake();

        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);
        $this->subscribe($account->user);
        Transaction::factory()->for($account)->create(['pointed_at' => null]);

        $sent = (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-31'));

        $this->assertSame(1, $sent);
        Notification::assertSentTo($account->user, PointingPeriodEnded::class);
    }

    public function test_nothing_goes_out_before_the_period_ends(): void
    {
        Notification::fake();

        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);
        $this->subscribe($account->user);
        Transaction::factory()->for($account)->create(['pointed_at' => null]);

        $this->assertSame(0, (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-20')));
        Notification::assertNothingSent();
    }

    public function test_a_straddling_period_reminds_on_its_real_end_day(): void
    {
        Notification::fake();

        // Du 5 au 4 : la fenêtre entamée le 5 août se termine le 4 septembre.
        $account = Account::factory()->create(['period_start_day' => 5, 'period_end_day' => 4]);
        $this->subscribe($account->user);
        Transaction::factory()->for($account)->create(['pointed_at' => null]);

        $this->assertSame(0, (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-31')));
        $this->assertSame(1, (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-09-04')));
    }

    public function test_a_fully_pointed_account_stays_silent(): void
    {
        Notification::fake();

        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);
        $this->subscribe($account->user);
        Transaction::factory()->for($account)->create(['pointed_at' => now()]);

        $this->assertSame(0, (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-31')));
        Notification::assertNothingSent();
    }

    public function test_a_user_without_subscription_receives_nothing(): void
    {
        Notification::fake();

        $account = Account::factory()->create(['period_start_day' => 1, 'period_end_day' => 31]);
        Transaction::factory()->for($account)->create(['pointed_at' => null]);

        $this->assertSame(0, (new SendPointingReminders)->handle(CarbonImmutable::parse('2026-08-31')));
        Notification::assertNothingSent();
    }
}
