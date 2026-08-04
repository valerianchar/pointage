<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    /**
     * Instances du mois issues des modèles récurrents, avec leur reste à pointer.
     */
    public function index(Request $request): Response
    {
        $month = CarbonImmutable::now();

        $instances = Transaction::query()
            ->whereIn('account_id', $request->user()->accounts()->select('accounts.id'))
            ->whereNotNull('recurring_transaction_id')
            ->whereBetween('occurred_on', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->with(['account', 'tag'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        return Inertia::render('Recurring/Index', [
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'instances' => TransactionResource::collection($instances)->resolve(),
            'pending_count' => $instances->reject(fn (Transaction $instance): bool => $instance->isPointed())->count(),
            'total_count' => $instances->count(),
        ]);
    }
}
