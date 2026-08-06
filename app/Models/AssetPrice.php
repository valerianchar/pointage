<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['asset_id', 'price_eur', 'fetched_at'])]
class AssetPrice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_eur' => 'decimal:10',
            'fetched_at' => 'datetime',
        ];
    }
}
