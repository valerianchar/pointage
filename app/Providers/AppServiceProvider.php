<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Les libellés de dates de l'interface sont en français (« 1 août », « Août 2026 »).
        Carbon::setLocale(config('app.locale'));
        Date::use(CarbonImmutable::class);
    }
}
