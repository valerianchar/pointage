<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Queries\RecurringInstances;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function __construct(private readonly RecurringInstances $recurringInstances) {}

    /**
     * Instances du mois issues des modèles récurrents, avec leur reste à pointer.
     *
     * Le mois entier est matérialisé d'avance : les instances dont le jour n'est
     * pas arrivé sont présentées « à venir », à part — elles ne sont ni à
     * pointer, ni comptées dans le reste à pointer.
     */
    public function index(Request $request): Response
    {
        $month = CarbonImmutable::now();
        $instances = $this->recurringInstances->forUserMonth($request->user(), $month);
        [$upcoming, $arrived] = $instances->partition(
            fn (Transaction $instance): bool => $instance->occurred_on->isFuture(),
        );

        return Inertia::render('Recurring/Index', [
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'instances' => TransactionResource::collection($arrived->values())->resolve(),
            'upcoming' => $upcoming->map(fn (Transaction $instance): array => [
                'id' => $instance->id,
                'label' => $instance->label,
                'amount_cents' => $instance->amount_cents,
                'account_name' => $instance->account->name,
                'tag' => $instance->tag?->name,
                'day_label' => 'le '.$instance->occurred_on->day,
            ])->values()->all(),
            'pending_count' => $arrived->reject(fn (Transaction $instance): bool => $instance->isPointed())->count(),
            'total_count' => $arrived->count(),
        ]);
    }
}
