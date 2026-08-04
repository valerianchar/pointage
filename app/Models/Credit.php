<?php

namespace App\Models;

use App\Support\MonthlyDate;
use Carbon\CarbonImmutable;
use Database\Factories\CreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'borrowed_cents', 'remaining_cents', 'monthly_cents', 'term_months', 'payment_day'])]
class Credit extends Model
{
    /** @use HasFactory<CreditFactory> */
    use HasFactory;

    private const MONTHS_PER_YEAR = 12;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_cents' => 'integer',
            'remaining_cents' => 'integer',
            'monthly_cents' => 'integer',
            'term_months' => 'integer',
            'payment_day' => 'integer',
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

    /**
     * Durée en clair : « 5 ans », « 18 mois », « 20 ans et 6 mois ».
     */
    protected function termLabel(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->term_months === null) {
                return null;
            }

            $years = intdiv($this->term_months, self::MONTHS_PER_YEAR);
            $months = $this->term_months % self::MONTHS_PER_YEAR;

            $yearsLabel = $years > 0 ? $years.($years > 1 ? ' ans' : ' an') : null;
            $monthsLabel = $months > 0 ? $months.' mois' : null;

            return implode(' et ', array_filter([$yearsLabel, $monthsLabel])) ?: '0 mois';
        });
    }

    /**
     * Prochaine échéance, à partir d'aujourd'hui.
     */
    protected function nextPaymentOn(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->payment_day === null
            ? null
            : MonthlyDate::onOrAfter(CarbonImmutable::now(), $this->payment_day));
    }

    /**
     * Nombre de mensualités qu'il reste à verser au rythme actuel — une estimation,
     * qui ignore les intérêts encore à courir.
     */
    protected function remainingInstalments(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->monthly_cents > 0
            ? (int) ceil($this->remaining_cents / $this->monthly_cents)
            : null);
    }
}
