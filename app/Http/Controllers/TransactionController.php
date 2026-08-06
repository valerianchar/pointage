<?php

namespace App\Http\Controllers;

use App\Actions\RecordTransaction;
use App\Enums\TransactionDirection;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Queries\UserAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(private readonly UserAccounts $userAccounts) {}

    public function create(Request $request): Response
    {
        // Les tags de tous les comptes sont envoyés d'un coup : changer de compte
        // dans le formulaire recharge les tags sans aller-retour serveur.
        $accounts = $this->userAccounts->withTags($request->user());
        $requestedAccountId = $request->integer('compte');

        $selectedAccount = $accounts->firstWhere('id', $requestedAccountId) ?? $accounts->first();

        return Inertia::render('Transactions/Create', [
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'directions' => TransactionDirection::options(),
            'selected_account_id' => $selectedAccount?->id,
        ]);
    }

    public function store(StoreTransactionRequest $request, RecordTransaction $recordTransaction): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        Gate::authorize('update', $account);

        $tag = $request->filled('tag_id') ? Tag::find($request->integer('tag_id')) : null;

        $transaction = $recordTransaction->handle(
            $account,
            $request->string('label')->trim()->value(),
            $request->signedAmountCents(),
            $tag,
            $request->boolean('is_recurring'),
            CarbonImmutable::now(),
            $request->filled('recurring_day') ? $request->integer('recurring_day') : null,
            $request->boolean('pointed'),
            $request->filled('occurred_on') ? CarbonImmutable::parse($request->string('occurred_on')->value()) : null,
        );

        $message = $transaction === null
            ? 'Récurrente programmée : elle apparaîtra le jour choisi.'
            : ($transaction->occurred_on->isFuture()
                ? 'Opération différée enregistrée : elle pèse déjà sur le solde.'
                : 'Opération ajoutée, en attente de pointage.');

        // Le pointage guidé ajoute des oublis sans quitter sa file : il reste sur place.
        if ($request->boolean('stay')) {
            return back()->with('success', $message);
        }

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', $message);
    }

    public function edit(Transaction $transaction): Response
    {
        Gate::authorize('update', $transaction);

        $transaction->load(['account.tags' => fn ($query) => $query->orderBy('id'), 'tag']);

        return Inertia::render('Transactions/Edit', [
            'transaction' => TransactionResource::make($transaction)->resolve(),
            'account' => AccountResource::make($transaction->account)->resolve(),
            'directions' => TransactionDirection::options(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $transaction->update([
            'label' => $request->string('label')->trim()->value(),
            'amount_cents' => $request->signedAmountCents(),
            'occurred_on' => $request->date('occurred_on')->toDateString(),
            'tag_id' => $request->filled('tag_id') ? $request->integer('tag_id') : null,
        ]);

        return redirect()
            ->route('accounts.show', $transaction->account)
            ->with('success', 'Opération modifiée.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $transaction->delete();

        return redirect()
            ->route('accounts.show', $transaction->account)
            ->with('success', 'Opération supprimée.');
    }
}
