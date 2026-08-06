<?php

namespace App\Http\Resources;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'icon' => $this->type->icon(),
            // Comptes à positions (crypto, PEA) : la fiche propose la saisie
            // d'avoirs, avec l'aide et la source de cours propres au type.
            'has_positions' => $this->type->hasPositions(),
            'position_placeholder' => $this->type->positionPlaceholder(),
            'price_source' => $this->type->assetProvider()?->label(),
            'balance_cents' => $this->balance_cents,
            // Opérations encore à rapprocher du relevé, tous mois confondus.
            'pending_count' => (int) ($this->getAttributes()['pending_count'] ?? 0),
            'period_start_day' => $this->period_start_day,
            'period_end_day' => $this->period_end_day,
            /*
             * Jours restants avant la fin de la période de pointage courante —
             * la bannière de rappel (J−5 à J−2) et l'écran « Pointage
             * obligatoire » (J−1, J−0) s'en nourrissent sur toutes les pages.
             */
            'days_until_period_end' => (int) CarbonImmutable::now()
                ->startOfDay()
                ->diffInDays($this->pointingPeriod(CarbonImmutable::now())->end, false),
            'url' => route('accounts.show', $this->id),
            'delete_url' => route('accounts.destroy', $this->id),
            'revalue_url' => route('accounts.revalue', $this->id),
            'period_url' => route('accounts.period', $this->id),
            // Résolue explicitement : une collection imbriquée non résolue se
            // sérialise enveloppée dans une clé « data » que le front n'attend pas.
            'tags' => $this->whenLoaded('tags', fn (): array => TagResource::collection($this->tags)->resolve()),
        ];
    }
}
