<?php

namespace App\Enums;

enum AccountType: string
{
    case Current = 'courant';
    case Savings = 'livret';
    case StockPlan = 'pea';
    case LifeInsurance = 'av';
    case Crypto = 'crypto';
    case Joint = 'joint';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Compte courant',
            self::Savings => 'Livret / Épargne',
            self::StockPlan => 'PEA',
            self::LifeInsurance => 'Assurance-vie',
            self::Crypto => 'Crypto',
            self::Joint => 'Compte joint',
        };
    }

    /**
     * Nom de l'icône Phosphor associée au type, tel qu'attendu par le front.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Current => 'ph-bank',
            self::Savings => 'ph-piggy-bank',
            self::StockPlan => 'ph-chart-line-up',
            self::LifeInsurance => 'ph-shield-check',
            self::Crypto => 'ph-currency-btc',
            self::Joint => 'ph-users',
        };
    }

    /**
     * Fournisseur de cours des comptes à positions : un compte crypto ou PEA ne
     * saisit pas de solde, il déclare ses avoirs et suit leur valeur de marché.
     */
    public function assetProvider(): ?AssetProvider
    {
        return match ($this) {
            self::Crypto => AssetProvider::Coingecko,
            self::StockPlan => AssetProvider::Yahoo,
            default => null,
        };
    }

    public function hasPositions(): bool
    {
        return $this->assetProvider() !== null;
    }

    /**
     * Aide à la saisie d'une position : le champ cherche par nom, mais accepte
     * aussi l'identifiant exact du fournisseur.
     */
    public function positionPlaceholder(): ?string
    {
        return match ($this) {
            self::Crypto => 'Nom ou identifiant — ex. bitcoin',
            self::StockPlan => 'Nom du fonds ou ticker — ex. Amundi MSCI World',
            default => null,
        };
    }

    /**
     * Tags créés automatiquement à la déclaration d'un compte de ce type.
     *
     * @return list<string>
     */
    public function defaultTags(): array
    {
        return match ($this) {
            self::Current => ['Courses', 'Loyer', 'Transport', 'Abonnements', 'Salaire'],
            self::Savings => ['Versement', 'Retrait', 'Intérêts'],
            self::StockPlan => ['Achat action', 'Vente', 'Dividende', 'Frais'],
            self::LifeInsurance => ['Versement', 'Arbitrage', 'Frais'],
            self::Crypto => ['Achat', 'Vente', 'Staking', 'Frais'],
            self::Joint => ['Courses', 'Factures', 'Loisirs', 'Versement'],
        };
    }

    /**
     * Description sérialisable de tous les types, pour l'écran « Déclarer un compte ».
     *
     * @return list<array{value: string, label: string, icon: string, default_tags: list<string>, has_positions: bool, position_placeholder: string|null, price_source: string|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'icon' => $type->icon(),
            'default_tags' => $type->defaultTags(),
            'has_positions' => $type->hasPositions(),
            'position_placeholder' => $type->positionPlaceholder(),
            'price_source' => $type->assetProvider()?->label(),
        ], self::cases());
    }
}
