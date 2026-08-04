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
     */
    public function index(Request $request): Response
    {
        $month = CarbonImmutable::now();
        $instances = $this->recurringInstances->forUserMonth($request->user(), $month);

        return Inertia::render('Recurring/Index', [
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'instances' => TransactionResource::collection($instances)->resolve(),
            'pending_count' => $instances->reject(fn (Transaction $instance): bool => $instance->isPointed())->count(),
            'total_count' => $instances->count(),
        ]);
    }
}
