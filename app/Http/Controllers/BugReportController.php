<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBugReportRequest;
use App\Notifications\BugReported;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class BugReportController extends Controller
{
    public function store(StoreBugReportRequest $request): RedirectResponse
    {
        $bugReport = $request->user()->bugReports()->create([
            'subject' => $request->subject(),
            'description' => $request->description(),
        ]);

        /*
         * Sans adresse configurée, le signalement reste consultable en base :
         * l'utilisateur n'a pas à savoir que l'instance n'a pas de mainteneur.
         */
        $maintainerEmail = config('pointage.maintainer_email');

        if ($maintainerEmail) {
            Notification::route('mail', $maintainerEmail)->notify(new BugReported($bugReport));
        }

        return back()->with('success', 'Signalement envoyé.');
    }
}
