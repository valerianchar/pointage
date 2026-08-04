<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Widgets affichés sur l'accueil. Nul tant que rien n'a été
             * personnalisé : tous les widgets sont alors visibles.
             */
            $table->json('dashboard_widgets')->nullable()->after('hide_balances');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_widgets');
        });
    }
};
