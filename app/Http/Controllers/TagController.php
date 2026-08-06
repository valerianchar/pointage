<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Account;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = $request->user()->accessibleAccounts()->orderBy('id')->get();
        $selectedAccount = $accounts->firstWhere('id', $request->integer('compte')) ?? $accounts->first();

        return Inertia::render('Tags/Index', [
            'selected_account_id' => $selectedAccount?->id,
            'type_note' => $selectedAccount === null
                ? null
                : "Tags par défaut du type « {$selectedAccount->type->label()} », plus les vôtres.",
            'tags' => $selectedAccount === null
                ? []
                : TagResource::collection(
                    $selectedAccount->tags()->withCount('transactions')->orderBy('id')->get()
                )->resolve(),
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        Gate::authorize('update', $account);

        $account->tags()->create(['name' => $request->string('name')->trim()->value()]);

        return back()->with('success', 'Tag ajouté.');
    }

    /**
     * Supprimer un tag ne supprime pas les opérations : elles se retrouvent sans tag.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $tag->delete();

        return back()->with('success', 'Tag supprimé.');
    }
}
