<?php

namespace App\Http\Resources;

use App\Models\AccountClosing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin AccountClosing
 */
class AccountClosingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // La clôture porte le nom du mois où sa période se termine.
            'month_label' => Str::ucfirst($this->period_end->translatedFormat('F Y')),
            'account_name' => $this->whenLoaded('account', fn (): string => $this->account->name),
            'pointed_expenses_cents' => $this->pointed_expenses_cents,
            'pointed_incomes_cents' => $this->pointed_incomes_cents,
            'variance_cents' => $this->variance_cents,
            'note' => $this->note,
        ];
    }
}
