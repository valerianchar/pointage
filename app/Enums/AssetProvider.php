<?php

namespace App\Enums;

enum AssetProvider: string
{
    case Coingecko = 'coingecko';
    case Yahoo = 'yahoo';

    public function label(): string
    {
        return match ($this) {
            self::Coingecko => 'CoinGecko',
            self::Yahoo => 'Yahoo Finance',
        };
    }

    /**
     * Forme attendue de l'identifiant d'actif : minuscules chez CoinGecko
     * (« bitcoin »), ticker boursier chez Yahoo (« CW8.PA »).
     */
    public function assetIdRules(): array
    {
        return match ($this) {
            self::Coingecko => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            self::Yahoo => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9.\-\^]+$/'],
        };
    }

    /**
     * Chaque source a sa casse canonique — la respecter évite les doublons
     * « cw8.pa » / « CW8.PA » dans les positions et les cours.
     */
    public function normalizeAssetId(string $assetId): string
    {
        return match ($this) {
            self::Coingecko => mb_strtolower(trim($assetId)),
            self::Yahoo => mb_strtoupper(trim($assetId)),
        };
    }

    public function assetIdFormatMessage(): string
    {
        return match ($this) {
            self::Coingecko => 'L\'identifiant CoinGecko s\'écrit en minuscules : « bitcoin », « ethereum »…',
            self::Yahoo => 'Le ticker s\'écrit comme sur sa fiche Yahoo Finance : « CW8.PA », « ESE.PA »…',
        };
    }

    public function unknownAssetMessage(string $assetId): string
    {
        return match ($this) {
            self::Coingecko => "Aucun actif « {$assetId} » chez CoinGecko. L'identifiant figure dans l'URL de sa fiche coingecko.com.",
            self::Yahoo => "Aucun titre « {$assetId} » chez Yahoo Finance, ou son cours n'est pas en euros. Le ticker figure sur sa fiche finance.yahoo.com — ex. « CW8.PA » pour un ETF d'Euronext Paris.",
        };
    }
}
