<?php

namespace App\Http\Controllers;

use App\Actions\SettleAccountDeletion;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountDeletionRequestController extends Controller
{
    /**
     * Un votant donne son accord ; le dernier accord emporte le compte.
     */
    public function approve(Request $request, AccountDeletionRequest $deletionRequest, SettleAccountDeletion $settle): RedirectResponse
    {
        abort_unless($deletionRequest->isVoter($request->user()), 403);

        $accountName = $deletionRequest->account->name;

        if ($settle->approve($deletionRequest, $request->user())) {
            return redirect()
                ->route('dashboard')
                ->with('success', "« {$accountName} » supprimé, d'un commun accord.");
        }

        return back()->with('success', 'Accord enregistré — en attente des autres membres.');
    }

    /**
     * Un refus — ou le renoncement du demandeur — et le compte reste.
     */
    public function refuse(Request $request, AccountDeletionRequest $deletionRequest, SettleAccountDeletion $settle): RedirectResponse
    {
        abort_unless($deletionRequest->isVoter($request->user()), 403);

        $isRequester = $deletionRequest->requested_by === $request->user()->id;
        $settle->refuse($deletionRequest);

        return back()->with('success', $isRequester
            ? 'Demande de suppression annulée.'
            : 'Suppression refusée — le compte reste.');
    }
}
