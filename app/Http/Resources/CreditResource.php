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
            'term_months' => $this->term_months,
            'term_label' => $this->term_label,
            'payment_day' => $this->payment_day,
            'next_payment_label' => $this->next_payment_on?->translatedFormat('j F'),
            'remaining_instalments' => $this->remaining_instalments,
            'account_name' => $this->whenLoaded('account', fn (): string => $this->account->name),
            'url' => route('credits.destroy', $this->id),
        ];
    }
}
