<?php

namespace App\Models;

use Database\Factories\AccountClosingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'period_start',
    'period_end',
    'theoretical_balance_cents',
    'real_balance_cents',
    'pointed_expenses_cents',
    'pointed_incomes_cents',
    'note',
])]
class AccountClosing extends Model
{
    /** @use HasFactory<AccountClosingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'theoretical_balance_cents' => 'integer',
            'real_balance_cents' => 'integer',
            'pointed_expenses_cents' => 'integer',
            'pointed_incomes_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Écart entre la banque et l'application : positif quand le compte réel est
     * au-dessus du solde pointé.
     */
    protected function varianceCents(): Attribute
    {
        return Attribute::get(fn (): int => $this->real_balance_cents - $this->theoretical_balance_cents);
    }
}
