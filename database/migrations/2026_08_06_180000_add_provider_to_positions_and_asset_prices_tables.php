<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Les positions ne sont plus seulement crypto : un PEA déclare ses ETF, et
     * chaque actif se cote chez son fournisseur — CoinGecko pour les cryptos,
     * Yahoo Finance pour les titres. Le couple (fournisseur, identifiant)
     * devient la clé d'un cours : « bitcoin » chez CoinGecko et un éventuel
     * ticker homonyme chez Yahoo ne se marchent pas dessus.
     */
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->string('provider', 16)->default('coingecko')->after('account_id');
        });

        Schema::table('asset_prices', function (Blueprint $table) {
            $table->string('provider', 16)->default('coingecko')->after('id');
            $table->dropUnique(['asset_id']);
            $table->unique(['provider', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_prices', function (Blueprint $table) {
            $table->dropUnique(['provider', 'asset_id']);
            $table->unique(['asset_id']);
            $table->dropColumn('provider');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
