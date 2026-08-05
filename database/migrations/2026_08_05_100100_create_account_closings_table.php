<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            /*
             * Bornes réelles de la période clôturée, figées au moment de la
             * clôture : changer la période de pointage ensuite ne réécrit pas
             * l'historique.
             */
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('theoretical_balance_cents');
            $table->bigInteger('real_balance_cents');
            /*
             * Totaux pointés figés eux aussi : l'historique doit rester lisible
             * même quand des opérations sont pointées ou ajoutées après coup.
             */
            $table->bigInteger('pointed_expenses_cents');
            $table->bigInteger('pointed_incomes_cents');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_closings');
    }
};
