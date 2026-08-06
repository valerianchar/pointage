<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Actions\DeleteAccount;
use App\Actions\EnsureAssetPrices;
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
use App\Queries\TagSpending;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
            ->loadCount(['transactions as pending_count' => fn ($query) => $query->whereNull('pointed_at')]);

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
        ]);
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

        $account = $createAccount->handle(
            $request->user(),
            $request->string('name')->trim()->value(),
            $type,
            $initialBalanceCents,
            $positions,
        );

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', $positions === []
                ? 'Compte créé, avec ses tags par défaut.'
                : 'Compte créé — son solde suivra les cours chaque nuit.');
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

    public function destroy(Account $account, DeleteAccount $deleteAccount): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $deleteAccount->handle($account);

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
     * @return Collection<int, Transaction>
     */
    private function visibleTransactions(Account $account, CarbonImmutable $month): Collection
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
