<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pointage guidé : les opérations non pointées du compte défilent une à une,
 * relevé de banque en main. C'est la cible du rappel de fin de période.
 */
class PointingSessionController extends Controller
{
    public function show(Account $account): Response
    {
        Gate::authorize('view', $account);

        $account->loadSum('transactions', 'amount_cents');

        $pending = $account->transactions()
            ->with('tag')
            ->whereNull('pointed_at')
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $period = $account->pointingPeriod(CarbonImmutable::now());

        return Inertia::render('Pointing/Session', [
            'account' => AccountResource::make($account)->resolve(),
            'period_label' => Str::ucfirst($period->label()),
            'transactions' => TransactionResource::collection($pending)->resolve(),
            'tags' => TagResource::collection($account->tags()->orderBy('id')->get())->resolve(),
        ]);
    }
}
