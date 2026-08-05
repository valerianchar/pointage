<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPointingPeriodController;
use App\Http\Controllers\Auth\AppLockController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BugReportController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardWidgetController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ScheduledTaskController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionPointingController;
use Illuminate\Support\Facades\Route;

/*
 * Déclencheur des tâches planifiées pour un hébergeur sans cron. Protégé par un
 * jeton, et inexistant tant qu'aucun jeton n'est configuré.
 */
Route::post('/taches/recurrentes', ScheduledTaskController::class)
    ->middleware('throttle:6,1')
    ->name('tasks.recurring');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store']);

    Route::middleware('registration')->group(function () {
        Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
        // Créer un profil est rare : freiner ici coupe la création de comptes en masse.
        Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Verrouillage : la session reste ouverte, l'application se cache derrière un écran.
    Route::get('/verrouillage', [AppLockController::class, 'show'])->name('lock.show');
    Route::post('/verrouillage', [AppLockController::class, 'store'])->name('lock.store');
    Route::delete('/verrouillage', [AppLockController::class, 'destroy'])->name('lock.destroy');
    Route::post('/verrouillage/biometrie', [AppLockController::class, 'destroyAfterDeviceConfirmation'])
        ->name('lock.biometric');

    Route::middleware('unlocked')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/nouveau-compte', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/comptes', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/compte/{account}', [AccountController::class, 'show'])->name('accounts.show');

        Route::get('/ajouter', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/operations', [TransactionController::class, 'store'])->name('transactions.store');
        Route::patch('/operations/{transaction}/pointage', [TransactionPointingController::class, 'update'])
            ->name('transactions.pointing');

        Route::get('/recurrentes', [RecurringTransactionController::class, 'index'])->name('recurring.index');

        Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
        Route::post('/credits', [CreditController::class, 'store'])->name('credits.store');
        Route::delete('/credits/{credit}', [CreditController::class, 'destroy'])->name('credits.destroy');

        Route::get('/bilan', [ClosingController::class, 'index'])->name('closings.index');
        Route::post('/clotures', [ClosingController::class, 'store'])->name('closings.store');
        Route::patch('/compte/{account}/periode', [AccountPointingPeriodController::class, 'update'])
            ->name('accounts.period');

        Route::patch('/widgets', [DashboardWidgetController::class, 'update'])->name('widgets.update');

        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // Chaque signalement part en e-mail au mainteneur : le débit doit rester humain.
        Route::post('/signalements', [BugReportController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('bug-reports.store');

        Route::patch('/confidentialite', [PrivacyController::class, 'update'])->name('privacy.update');
    });
});
