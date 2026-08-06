<?php

namespace App\Models;

use App\Enums\DashboardWidget;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'hide_balances', 'dashboard_widgets'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hide_balances' => 'boolean',
            'dashboard_widgets' => 'array',
        ];
    }

    /**
     * Widgets visibles sur l'accueil. Tant que rien n'a été personnalisé, tous le
     * sont ; un widget retiré du code disparaît des préférences enregistrées.
     *
     * @return list<string>
     */
    public function enabledDashboardWidgets(): array
    {
        if ($this->dashboard_widgets === null) {
            return DashboardWidget::defaultKeys();
        }

        return array_values(array_intersect(DashboardWidget::defaultKeys(), $this->dashboard_widgets));
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<BugReport, $this> */
    public function bugReports(): HasMany
    {
        return $this->hasMany(BugReport::class);
    }

    /**
     * Initiales affichées dans le pied de la sidebar (« MO » sur la maquette).
     */
    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode(''));
    }
}
