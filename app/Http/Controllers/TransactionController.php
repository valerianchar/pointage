<?php

namespace App\Http\Controllers;

use App\Actions\RecordTransaction;
use App\Enums\TransactionDirection;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Tag;
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
        );

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', $transaction === null
                ? 'Récurrente programmée : elle apparaîtra le jour choisi.'
                : 'Opération ajoutée, en attente de pointage.');
    }
}
