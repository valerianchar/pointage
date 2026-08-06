<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\RecurringTransaction;
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
     * Instances du mois issues des modèles récurrents, avec leur reste à pointer,
     * plus les modèles dont le jour n'est pas encore arrivé.
     */
    public function index(Request $request): Response
    {
        $month = CarbonImmutable::now();
        $instances = $this->recurringInstances->forUserMonth($request->user(), $month);
        $upcoming = $this->recurringInstances->upcomingForUserMonth($request->user(), $month);

        return Inertia::render('Recurring/Index', [
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'instances' => TransactionResource::collection($instances)->resolve(),
            'upcoming' => $upcoming->map(fn (RecurringTransaction $template): array => [
                'id' => $template->id,
                'label' => $template->label,
                'amount_cents' => $template->amount_cents,
                'account_name' => $template->account->name,
                'tag' => $template->tag?->name,
                'day_label' => 'le '.$template->day_of_month,
            ])->values()->all(),
            'pending_count' => $instances->reject(fn (Transaction $instance): bool => $instance->isPointed())->count(),
            'total_count' => $instances->count(),
        ]);
    }
}
