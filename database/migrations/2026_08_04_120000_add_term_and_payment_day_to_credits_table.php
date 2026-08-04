<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            /*
             * Durée stockée en mois même si l'écran la saisit en années : un prêt
             * de dix-huit mois reste alors représentable sans nouvelle migration.
             *
             * Les deux colonnes acceptent le nul pour les crédits déclarés avant
             * leur arrivée ; la validation les exige pour tout nouveau crédit.
             */
            $table->unsignedSmallInteger('term_months')->nullable()->after('monthly_cents');
            $table->unsignedTinyInteger('payment_day')->nullable()->after('term_months');
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn(['term_months', 'payment_day']);
        });
    }
};
