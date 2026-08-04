<?php

namespace App\Enums;

enum DashboardWidget: string
{
    case Wealth = 'patrimoine';
    case MonthlyFlow = 'flux';
    case SavingsRate = 'epargne';
    case Pending = 'pointage';
    case DailyAverage = 'moyenne';
    case RecurringCharge = 'recurrentes';
    case Credits = 'credits';
    case BalanceHistory = 'evolution';
    case TagSpending = 'tags';
    case TopExpenses = 'top';
    case AccountShare = 'repartition';

    /**
     * Libellé du sélecteur « Widgets affichés ».
     */
    public function label(): string
    {
        return match ($this) {
            self::Wealth => 'Patrimoine',
            self::MonthlyFlow => 'Flux du mois',
            self::SavingsRate => "Taux d'épargne",
            self::Pending => 'À pointer',
            self::DailyAverage => 'Moyenne / jour',
            self::RecurringCharge => 'Récurrentes',
            self::Credits => 'Crédits',
            self::BalanceHistory => 'Évolution du solde',
            self::TagSpending => 'Dépenses par tag',
            self::TopExpenses => 'Top dépenses',
            self::AccountShare => 'Répartition par compte',
        };
    }

    /**
     * Titre porté par la carte elle-même, plus explicite que le libellé du chip.
     */
    public function cardTitle(): string
    {
        return match ($this) {
            self::DailyAverage => 'Dépense moy. / jour',
            self::RecurringCharge => 'Charge récurrente',
            self::Credits => 'Crédits — restant dû',
            self::TagSpending => 'Dépenses par tag — tous comptes',
            self::TopExpenses => 'Top dépenses du mois',
            default => $this->label(),
        };
    }

    /**
     * Nombre de colonnes occupées dans la grille de quatre.
     */
    public function columnSpan(): int
    {
        return match ($this) {
            self::BalanceHistory, self::TagSpending, self::TopExpenses, self::AccountShare => 2,
            default => 1,
        };
    }

    /**
     * Tous les widgets sont visibles tant que rien n'a été personnalisé.
     *
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        return array_map(fn (self $widget): string => $widget->value, self::cases());
    }

    /**
     * @param  list<string>  $enabledKeys
     * @return list<array{key: string, label: string, title: string, span: int, enabled: bool}>
     */
    public static function describe(array $enabledKeys): array
    {
        return array_map(fn (self $widget): array => [
            'key' => $widget->value,
            'label' => $widget->label(),
            'title' => $widget->cardTitle(),
            'span' => $widget->columnSpan(),
            'enabled' => in_array($widget->value, $enabledKeys, true),
        ], self::cases());
    }
}
