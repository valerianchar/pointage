<?php

namespace App\Http\Middleware;

use App\Http\Resources\AccountResource;
use App\Http\Resources\BugReportResource;
use App\Queries\UserAccounts;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly UserAccounts $userAccounts) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials,
                    'hide_balances' => $user->hide_balances,
                ],
            ],
            /*
             * La sidebar desktop liste les comptes sur tous les écrans : ils sont
             * donc partagés, en une requête agrégée, plutôt que répétés page à page.
             */
            'accounts' => $user === null
                ? []
                : AccountResource::collection($this->userAccounts->forSidebar($user))->resolve(),
            /*
             * La modale « Signaler un bug » s'ouvre depuis n'importe quel écran :
             * ses signalements sont donc partagés, comme les comptes.
             */
            'bug_reports' => $user === null
                ? []
                : BugReportResource::collection($user->bugReports()->latest('id')->get())->resolve(),
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
            // L'écran de connexion n'affiche « Créer un profil » que si c'est possible.
            'registration_open' => config('pointage.registration_open'),
        ];
    }
}
