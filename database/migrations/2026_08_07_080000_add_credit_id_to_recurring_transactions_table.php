<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * La mensualité d'un crédit est une récurrente comme une autre : déclarer
     * le crédit crée son modèle, marqué d'où il vient — supprimer le crédit
     * sait alors quel modèle éteindre.
     */
    public function up(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->foreignId('credit_id')->nullable()->after('tag_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_id');
        });
    }
};
