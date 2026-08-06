<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Support\PointingPeriod;
use Carbon\CarbonInterface;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'initial_balance_cents', 'period_start_day', 'period_end_day'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance_cents' => 'integer',
            'period_start_day' => 'integer',
            'period_end_day' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<RecurringTransaction, $this> */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /** @return HasMany<Credit, $this> */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** @return HasMany<AccountClosing, $this> */
    public function closings(): HasMany
    {
        return $this->hasMany(AccountClosing::class);
    }

    /**
     * Fenêtre de pointage qui contient la date donnée, selon les jours du compte.
     */
    public function pointingPeriod(CarbonInterface $date): PointingPeriod
    {
        return PointingPeriod::containing($date, $this->period_start_day, $this->period_end_day);
    }

    /**
     * Solde courant en centimes : point de départ plus toutes les opérations.
     *
     * Réutilise l'agrégat de `withSum('transactions', 'amount_cents')` quand il a
     * été chargé, pour ne pas relancer une requête par compte affiché.
     */
    protected function balanceCents(): Attribute
    {
        return Attribute::get(fn (): int => $this->initial_balance_cents + $this->transactionsTotalCents());
    }

    private function transactionsTotalCents(): int
    {
        return (int) ($this->attributes['transactions_sum_amount_cents']
            ?? $this->transactions()->sum('amount_cents'));
    }
}
