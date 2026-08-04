<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CreditResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
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
            'add_url' => route('transactions.create', ['compte' => $account->id]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create', [
            'types' => AccountType::options(),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $account = $createAccount->handle(
            $request->user(),
            $request->string('name')->trim()->value(),
            $request->accountType(),
            $request->initialBalanceCents(),
        );

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Compte créé, avec ses tags par défaut.');
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
