<?php

namespace App\Models;

use Database\Factories\CreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'borrowed_cents', 'remaining_cents', 'monthly_cents'])]
class Credit extends Model
{
    /** @use HasFactory<CreditFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_cents' => 'integer',
            'remaining_cents' => 'integer',
            'monthly_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Part du capital déjà remboursée, bornée à 0–100 : un capital restant saisi
     * de travers ne doit pas produire de jauge absurde.
     */
    protected function repaidPercent(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->borrowed_cents <= 0) {
                return 0;
            }

            $repaid = ($this->borrowed_cents - $this->remaining_cents) / $this->borrowed_cents * 100;

            return (int) max(0, min(100, round($repaid)));
        });
    }
}
