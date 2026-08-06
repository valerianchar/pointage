<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Positions d'un compte de marché : « 0,05 bitcoin », « 2 ethereum ».
         * Les quantités crypto débordent largement du modèle en centimes
         * entiers : décimal à dix chiffres après la virgule.
         */
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            // Identifiant CoinGecko de l'actif : « bitcoin », « ethereum »…
            $table->string('asset_id', 64);
            $table->string('label', 64);
            $table->decimal('quantity', 28, 10);
            $table->timestamps();

            $table->unique(['account_id', 'asset_id']);
        });

        /*
         * Dernier cours connu de chaque actif, en euros. Un seul enregistrement
         * par actif : l'historique des valorisations vit déjà dans les
         * opérations de réévaluation. Le cours garde sa date : en cas de panne
         * de l'API, l'interface montre l'âge de la donnée plutôt que de mentir.
         */
        Schema::create('asset_prices', function (Blueprint $table) {
            $table->id();
            $table->string('asset_id', 64)->unique();
            $table->decimal('price_eur', 24, 10);
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('asset_prices');
    }
};
