<?php

namespace App\Http\Resources;

use App\Models\Account;
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
            'balance_cents' => $this->balance_cents,
            // Opérations encore à rapprocher du relevé, tous mois confondus.
            'pending_count' => (int) ($this->getAttributes()['pending_count'] ?? 0),
            'period_start_day' => $this->period_start_day,
            'period_end_day' => $this->period_end_day,
            'url' => route('accounts.show', $this->id),
            'pointing_url' => route('pointing.session', $this->id),
            'period_url' => route('accounts.period', $this->id),
            // Résolue explicitement : une collection imbriquée non résolue se
            // sérialise enveloppée dans une clé « data » que le front n'attend pas.
            'tags' => $this->whenLoaded('tags', fn (): array => TagResource::collection($this->tags)->resolve()),
        ];
    }
}
