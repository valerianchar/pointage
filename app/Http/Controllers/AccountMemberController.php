<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Events\AccountActivityChanged;
use App\Events\JointAccountInvited;
use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use App\Notifications\JointAccountInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountMemberController extends Controller
{
    /**
     * Invite un utilisateur sur un compte joint, par son e-mail exact — pas de
     * recherche par nom : elle exposerait la liste des inscrits. L'invitation
     * n'ouvre aucun accès tant qu'elle n'est pas acceptée.
     */
    public function store(Request $request, Account $account): RedirectResponse
    {
        Gate::authorize('manageMembers', $account);
        abort_unless($account->type === AccountType::Joint, 404);

        $validated = $request->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Indiquez l\'e-mail de la personne à inviter.', 'email.email' => 'Cet e-mail est invalide.'],
        );

        $invitee = User::query()->where('email', $validated['email'])->first();

        if ($invitee === null) {
            throw ValidationException::withMessages([
                'email' => 'Aucun utilisateur avec cet e-mail — il doit d\'abord créer son profil.',
            ]);
        }

        if ($invitee->is($request->user())) {
            throw ValidationException::withMessages(['email' => 'Vous êtes déjà chez vous sur ce compte.']);
        }

        if ($account->members()->where('user_id', $invitee->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Cette personne est déjà invitée sur ce compte.']);
        }

        $account->members()->create([
            'user_id' => $invitee->id,
            'invited_by' => $request->user()->id,
        ]);

        // App ouverte : la bannière apparaît en direct. Fermée : le push prend le relais.
        JointAccountInvited::dispatch($account, $request->user(), $invitee->id);

        if ($invitee->pushSubscriptions()->exists()) {
            $invitee->notify(new JointAccountInvitation($account, $request->user()));
        }

        return back()->with('success', "Invitation envoyée à {$invitee->name} — en attente de sa réponse.");
    }

    /**
     * L'invité accepte : le compte devient le sien aussi, partout — et la
     * salle d'attente des autres membres se met à jour en direct.
     */
    public function accept(Request $request, AccountMember $member): RedirectResponse
    {
        abort_unless($member->user_id === $request->user()->id, 403);

        if (! $member->isAccepted()) {
            $member->update(['accepted_at' => now()]);
            $this->refreshOtherMembers($member->account, $request->user());
        }

        return back()->with('success', "« {$member->account->name} » vous est ouvert — il apparaît dans vos comptes.");
    }

    /**
     * Trois départs, un seul geste : l'invité refuse, le membre quitte, le
     * propriétaire retire. L'historique du compte reste intact — seul l'accès
     * disparaît.
     */
    public function destroy(Request $request, AccountMember $member): RedirectResponse
    {
        $isSelf = $member->user_id === $request->user()->id;
        $isOwner = $member->account->user_id === $request->user()->id;

        abort_unless($isSelf || $isOwner, 403);

        $message = match (true) {
            $isSelf && ! $member->isAccepted() => 'Invitation refusée.',
            $isSelf => "Vous avez quitté « {$member->account->name} ».",
            default => "{$member->user->name} n'a plus accès à « {$member->account->name} ».",
        };

        $member->delete();
        $this->refreshOtherMembers($member->account, $request->user());

        return back()->with('success', $message);
    }

    /**
     * Rafraîchit en direct l'écran des autres membres — salle d'attente,
     * liste des membres — après une réponse ou un départ.
     */
    private function refreshOtherMembers(Account $account, User $actor): void
    {
        $recipientIds = $account->members()->accepted()->pluck('user_id')
            ->push($account->user_id)
            ->unique()
            ->reject(fn (int $userId): bool => $userId === $actor->id)
            ->values();

        if ($recipientIds->isNotEmpty()) {
            AccountActivityChanged::dispatch($account->id, $recipientIds->all());
        }
    }
}
