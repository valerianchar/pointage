<?php

namespace Tests\Feature;

use App\Models\BugReport;
use App\Models\User;
use App\Notifications\BugReported;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BugReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bug_report_is_stored_and_mailed_to_the_maintainer(): void
    {
        Notification::fake();
        config(['pointage.maintainer_email' => 'mainteneur@exemple.fr']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/signalements', [
                'subject' => 'Le pointage ne s\'enregistre pas',
                'description' => 'Cliquer sur le rond ne change rien.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $report = BugReport::sole();
        $this->assertSame('Le pointage ne s\'enregistre pas', $report->subject);
        $this->assertSame('envoye', $report->status->value);
        $this->assertTrue($report->user->is($user));

        Notification::assertSentOnDemand(
            BugReported::class,
            fn (BugReported $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'mainteneur@exemple.fr',
        );
    }

    public function test_without_subject_the_description_becomes_the_subject(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/signalements', [
                'description' => 'Le graphique de l\'accueil disparaît quand je masque les soldes.',
            ])
            ->assertRedirect();

        $this->assertSame('Le graphique de l\'accueil disparaît quan…', BugReport::sole()->subject);
    }

    public function test_the_description_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/signalements', ['subject' => 'Sans description'])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, BugReport::count());
    }

    public function test_without_maintainer_email_the_report_is_stored_but_nothing_is_sent(): void
    {
        Notification::fake();
        config(['pointage.maintainer_email' => null]);

        $this->actingAs(User::factory()->create())
            ->post('/signalements', ['description' => 'Un bug sans destinataire.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, BugReport::count());
        Notification::assertNothingSent();
    }

    public function test_own_reports_are_shared_with_every_screen_newest_first(): void
    {
        $user = User::factory()->create();
        BugReport::factory()->for($user)->about('Premier signalement')->create();
        BugReport::factory()->for($user)->about('Second signalement')->resolved()->create();
        BugReport::factory()->create(); // celui d'un autre profil

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bug_reports', 2)
                ->where('bug_reports.0.subject', 'Second signalement')
                ->where('bug_reports.0.status', 'resolu')
                ->where('bug_reports.0.status_label', 'Résolu')
                ->where('bug_reports.1.subject', 'Premier signalement')
                ->where('bug_reports.1.status_label', 'Envoyé'));
    }

    public function test_the_resolve_command_closes_a_report(): void
    {
        $report = BugReport::factory()->about('Bug corrigé')->create();

        $this->artisan('bug-reports:resolve', ['id' => $report->id])
            ->expectsOutputToContain('marqué résolu')
            ->assertSuccessful();

        $this->assertSame('resolu', $report->fresh()->status->value);
    }

    public function test_the_resolve_command_lists_open_reports_when_no_id_is_given(): void
    {
        BugReport::factory()->about('Toujours ouvert')->create();
        BugReport::factory()->resolved()->create();

        $this->artisan('bug-reports:resolve')
            ->expectsOutputToContain('Toujours ouvert')
            ->assertSuccessful();
    }
}
