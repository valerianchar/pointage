<?php

namespace App\Models;

use App\Enums\AssetProvider;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider', 'asset_id', 'label', 'quantity'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AssetProvider::class,
            'quantity' => 'decimal:10',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Valeur de la position au dernier cours connu, en centimes — ou null tant
     * qu'aucun cours n'a été récupéré.
     */
    public function valueCents(?AssetPrice $price): ?int
    {
        return $price === null
            ? null
            : (int) round((float) $this->quantity * (float) $price->price_eur * 100);
    }
}
