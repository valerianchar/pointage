<?php

namespace App\Enums;

enum TransactionDirection: string
{
    case Expense = 'depense';
    case Income = 'ajout';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Dépense',
            self::Income => 'Ajout',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::Expense => 'Ajouter la dépense',
            self::Income => "Ajouter l'entrée",
        };
    }

    /**
     * Applique le signe du sens à un montant saisi en valeur absolue.
     */
    public function signedCents(int $cents): int
    {
        return match ($this) {
            self::Expense => -abs($cents),
            self::Income => abs($cents),
        };
    }

    /**
     * @return list<array{value: string, label: string, submit_label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $direction): array => [
            'value' => $direction->value,
            'label' => $direction->label(),
            'submit_label' => $direction->submitLabel(),
        ], self::cases());
    }
}
