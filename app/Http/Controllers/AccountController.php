<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Actions\EnsureAssetPrices;
use App\Actions\RequestAccountDeletion;
use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CreditResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\AssetPrice;
use App\Models\Position;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\JointAccountInvitation;
use App\Queries\TagSpending;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private readonly TagSpending $tagSpending) {}

    public function show(Request $request, Account $account): Response
    {
        Gate::authorize('view', $account);

        $month = CarbonImmutable::now();

        $account->loadSum('transactions', 'amount_cents')
            // Les opérations à venir attendent leur jour pour entrer dans le cycle.
            ->loadCount(['transactions as pending_count' => fn ($query) => $query
                ->whereNull('pointed_at')
                ->where('occurred_on', '<=', $month->toDateString())]);

        $transactions = $this->visibleTransactions($account, $month);

        return Inertia::render('Accounts/Show', [
            'account' => AccountResource::make($account)->resolve(),
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'transactions' => TransactionResource::collection($transactions)->resolve(),
            'tag_spending' => $this->tagSpending->forAccountMonth($account, $month),
            'credits' => CreditResource::collection($account->credits()->orderBy('id')->get())->resolve(),
            'positions' => $this->positionsWithPrices($account),
            'add_url' => route('transactions.create', ['compte' => $account->id]),
            'members' => $this->membersOf($account, $request->user()),
            'can_invite' => $request->user()->can('manageMembers', $account),
            'invite_url' => route('members.store', $account->id),
            'deletion_request' => $this->deletionRequestOf($account, $request->user()),
        ]);
    }

    /**
     * Demande de suppression en cours sur ce compte, vue par le visiteur.
     *
     * @return array<string, mixed>|null
     */
    private function deletionRequestOf(Account $account, User $viewer): ?array
    {
        $deletionRequest = $account->deletionRequest()->with('requester')->first();

        if ($deletionRequest === null) {
            return null;
        }

        return [
            'requester_name' => $deletionRequest->requester->name,
            'is_requester' => $deletionRequest->requested_by === $viewer->id,
            'i_approved' => $deletionRequest->hasApprovalFrom($viewer),
            'approvals_count' => $deletionRequest->approvals()->count(),
            'voters_count' => $deletionRequest->voters()->count(),
            'approve_url' => route('deletions.approve', $deletionRequest->id),
            'refuse_url' => route('deletions.refuse', $deletionRequest->id),
        ];
    }

    /**
     * Membres d'un compte joint, propriétaire en tête — les autres types
     * n'en ont pas.
     *
     * @return list<array<string, mixed>>
     */
    private function membersOf(Account $account, User $viewer): array
    {
        if ($account->type !== AccountType::Joint) {
            return [];
        }

        $account->loadMissing('user');

        $rows = [[
            'id' => null,
            'name' => $account->user->name,
            'status' => 'owner',
            'is_me' => $account->user_id === $viewer->id,
            'remove_url' => null,
        ]];

        foreach ($account->members()->with('user')->orderBy('id')->get() as $member) {
            // Chacun peut se retirer lui-même ; le propriétaire peut retirer n'importe qui.
            $canRemove = $viewer->id === $member->user_id || $viewer->id === $account->user_id;

            $rows[] = [
                'id' => $member->id,
                'name' => $member->user->name,
                'status' => $member->isAccepted() ? 'member' : 'pending',
                'is_me' => $member->user_id === $viewer->id,
                'remove_url' => $canRemove ? route('members.destroy', $member->id) : null,
            ];
        }

        return $rows;
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create', [
            'types' => AccountType::options(),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $createAccount, EnsureAssetPrices $ensureAssetPrices): RedirectResponse
    {
        $type = $request->accountType();
        $positions = $request->positions();

        /*
         * Un compte à positions naît à la valeur de son portefeuille : les
         * cours sont récupérés sur-le-champ — ce qui valide chaque actif — et
         * le solde initial en découle. Pas de positions ? Solde saisi, comme
         * pour n'importe quel compte.
         */
        $initialBalanceCents = $positions === []
            ? $request->initialBalanceCents()
            : $this->portfolioValueCents($ensureAssetPrices, $type, $positions);

        // Un compte joint se partage : il naît avec au moins une invitation.
        $invitees = $type === AccountType::Joint
            ? $this->resolveInvitees($request->user(), $request->memberEmails())
            : collect();

        $account = $createAccount->handle(
            $request->user(),
            $request->string('name')->trim()->value(),
            $type,
            $initialBalanceCents,
            $positions,
            $invitees->pluck('id')->all(),
        );

        foreach ($invitees as $invitee) {
            if ($invitee->pushSubscriptions()->exists()) {
                $invitee->notify(new JointAccountInvitation($account, $request->user()));
            }
        }

        $message = match (true) {
            $invitees->isNotEmpty() => 'Compte joint créé — en attente de la réponse des membres invités.',
            $positions !== [] => 'Compte créé — son solde suivra les cours chaque nuit.',
            default => 'Compte créé, avec ses tags par défaut.',
        };

        return redirect()->route('accounts.show', $account)->with('success', $message);
    }

    /**
     * Résout les e-mails invités en utilisateurs — chacun doit exister, et un
     * compte joint sans personne à inviter n'a pas de raison d'être.
     *
     * @param  list<string>  $emails
     * @return Collection<int, User>
     */
    private function resolveInvitees(User $owner, array $emails): Collection
    {
        if ($emails === []) {
            throw ValidationException::withMessages([
                'members' => 'Un compte joint se partage : invitez au moins une personne.',
            ]);
        }

        return collect($emails)->map(function (string $email) use ($owner): User {
            $invitee = User::query()->where('email', $email)->first();

            if ($invitee === null) {
                throw ValidationException::withMessages([
                    'members' => "Aucun utilisateur avec « {$email} » — il doit d'abord créer son profil.",
                ]);
            }

            if ($invitee->is($owner)) {
                throw ValidationException::withMessages([
                    'members' => 'Inutile de vous inviter vous-même : vous êtes le propriétaire.',
                ]);
            }

            return $invitee;
        });
    }

    /**
     * Valeur du portefeuille déclaré, au cours du jour, en centimes.
     *
     * @param  list<array{asset_id: string, quantity: string}>  $positions
     */
    private function portfolioValueCents(EnsureAssetPrices $ensureAssetPrices, AccountType $type, array $positions): int
    {
        $prices = $ensureAssetPrices->handle(
            $type->assetProvider(),
            array_column($positions, 'asset_id'),
            'positions',
        );

        return (int) collect($positions)->sum(
            fn (array $position): float => round(
                (float) $position['quantity'] * (float) $prices[$position['asset_id']]->price_eur * 100
            )
        );
    }

    public function destroy(Request $request, Account $account, RequestAccountDeletion $requestAccountDeletion): RedirectResponse
    {
        Gate::authorize('delete', $account);

        /*
         * Compte partagé : la suppression devient une demande, chaque autre
         * membre devant donner son accord. Compte à soi : suppression directe.
         */
        if ($requestAccountDeletion->handle($account, $request->user()) !== null) {
            return back()->with('success', 'Demande envoyée aux autres membres — le compte sera supprimé quand chacun aura accepté.');
        }

        return redirect()
            ->route('dashboard')
            ->with('success', "Compte « {$account->name} » supprimé, avec tout son historique.");
    }

    /**
     * Positions du compte avec leur dernier cours connu et sa fraîcheur.
     *
     * @return list<array<string, mixed>>
     */
    private function positionsWithPrices(Account $account): array
    {
        $positions = $account->positions()->orderBy('id')->get();

        if ($positions->isEmpty()) {
            return [];
        }

        $prices = AssetPrice::query()
            ->whereIn('asset_id', $positions->pluck('asset_id'))
            ->get()
            ->keyBy(fn (AssetPrice $price): string => $price->provider->value.':'.$price->asset_id);

        return $positions->map(function (Position $position) use ($prices): array {
            $price = $prices->get($position->provider->value.':'.$position->asset_id);

            return [
                'id' => $position->id,
                'asset_id' => $position->asset_id,
                'label' => $position->label,
                'quantity' => rtrim(rtrim($position->quantity, '0'), '.'),
                'price_cents' => $price === null ? null : (int) round((float) $price->price_eur * 100),
                'value_cents' => $position->valueCents($price),
                'price_date_label' => $price?->fetched_at->translatedFormat('j M, H:i'),
                'delete_url' => route('positions.destroy', $position->id),
            ];
        })->all();
    }

    /**
     * Opérations du mois affiché, plus toutes celles restées à pointer les mois
     * précédents : une opération non pointée ne doit jamais devenir inatteignable.
     *
     * @return EloquentCollection<int, Transaction>
     */
    private function visibleTransactions(Account $account, CarbonImmutable $month): EloquentCollection
    {
        return $account->transactions()
            ->with('tag')
            ->where(fn ($query) => $query
                ->whereBetween('occurred_on', [
                    $month->startOfMonth()->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ])
                ->orWhereNull('pointed_at'))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();
    }
}
