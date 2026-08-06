<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'amount_cents' => $this->amount_cents,
            'tag' => $this->tag?->name,
            'date_label' => $this->occurred_on->translatedFormat('j F'),
            'occurred_on' => $this->occurred_on->toDateString(),
            'is_pointed' => $this->isPointed(),
            'is_recurring' => $this->recurring_transaction_id !== null,
            'account_name' => $this->whenLoaded('account', fn (): string => $this->account->name),
            'tag_id' => $this->tag_id,
            'pointing_url' => route('transactions.pointing', $this->id),
            'edit_url' => route('transactions.edit', $this->id),
            'update_url' => route('transactions.update', $this->id),
            'delete_url' => route('transactions.destroy', $this->id),
        ];
    }
}
