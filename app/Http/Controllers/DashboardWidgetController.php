<?php

namespace App\Http\Controllers;

use App\Enums\DashboardWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardWidgetController extends Controller
{
    /**
     * Enregistre les widgets affichés sur l'accueil. Une liste vide est valable :
     * on a le droit de tout masquer.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'widgets' => ['present', 'array'],
            'widgets.*' => [Rule::enum(DashboardWidget::class)],
        ]);

        $request->user()->update([
            'dashboard_widgets' => array_values(array_unique($validated['widgets'])),
        ]);

        return back();
    }
}
