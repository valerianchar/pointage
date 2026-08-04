<?php

namespace App\Http\Resources;

use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Credit
 */
class CreditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'borrowed_cents' => $this->borrowed_cents,
            'remaining_cents' => $this->remaining_cents,
            'monthly_cents' => $this->monthly_cents,
            'repaid_percent' => $this->repaid_percent,
            'account_name' => $this->whenLoaded('account', fn (): string => $this->account->name),
            'url' => route('credits.destroy', $this->id),
        ];
    }
}
