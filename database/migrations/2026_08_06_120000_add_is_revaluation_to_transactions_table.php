<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            /*
             * Une réévaluation recale le solde d'un compte de marché (PEA,
             * crypto…) sur sa valeur réelle. Elle compte dans le solde — c'est
             * son rôle — mais jamais dans les statistiques de flux : une baisse
             * de marché n'est pas une dépense.
             */
            $table->boolean('is_revaluation')->default(false)->after('pointed_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_revaluation');
        });
    }
};
