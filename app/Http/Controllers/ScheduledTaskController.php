<?php

namespace App\Http\Controllers;

use App\Actions\GenerateRecurringTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledTaskController extends Controller
{
    /**
     * Génère les opérations récurrentes du mois sur appel HTTP.
     *
     * Prévu pour un hébergeur sans cron : un service de cron externe appelle cette
     * route une fois par mois. L'action est idempotente, donc un appel de trop, un
     * réessai ou un rejeu ne crée aucun doublon.
     */
    public function __invoke(Request $request, GenerateRecurringTransactions $generateRecurringTransactions): JsonResponse
    {
        $expectedToken = config('pointage.tasks_token');

        // Sans jeton configuré, la route n'existe pas.
        abort_if(blank($expectedToken), 404);

        $presentedToken = $request->bearerToken();

        abort_unless(
            is_string($presentedToken) && hash_equals($expectedToken, $presentedToken),
            403,
        );

        return response()->json([
            'created' => $generateRecurringTransactions->handle(CarbonImmutable::now()),
        ]);
    }
}
