<?php

namespace App\Console\Commands;

use App\Enums\BugReportStatus;
use App\Models\BugReport;
use Illuminate\Console\Command;

/**
 * L'application n'a pas d'écran d'administration : le mainteneur clôt les
 * signalements depuis le serveur, une fois le bug corrigé.
 */
class ResolveBugReportCommand extends Command
{
    protected $signature = 'bug-reports:resolve
                            {id? : Identifiant du signalement (sans lui, la liste des signalements ouverts s\'affiche)}';

    protected $description = 'Marque un signalement de bug comme résolu';

    public function handle(): int
    {
        $reportId = $this->argument('id');

        if ($reportId === null) {
            return $this->listOpenReports();
        }

        $bugReport = BugReport::find($reportId);

        if ($bugReport === null) {
            $this->components->error("Aucun signalement n° {$reportId}.");

            return self::FAILURE;
        }

        $bugReport->update(['status' => BugReportStatus::Resolved]);

        $this->components->info("Signalement n° {$bugReport->id} « {$bugReport->subject} » marqué résolu.");

        return self::SUCCESS;
    }

    private function listOpenReports(): int
    {
        $openReports = BugReport::query()
            ->where('status', BugReportStatus::Sent)
            ->with('user')
            ->orderBy('id')
            ->get();

        if ($openReports->isEmpty()) {
            $this->components->info('Aucun signalement en attente.');

            return self::SUCCESS;
        }

        $this->table(
            ['N°', 'Sujet', 'De', 'Reçu le'],
            $openReports->map(fn (BugReport $report): array => [
                $report->id,
                $report->subject,
                $report->user->name,
                $report->created_at->translatedFormat('j F Y'),
            ]),
        );

        return self::SUCCESS;
    }
}
