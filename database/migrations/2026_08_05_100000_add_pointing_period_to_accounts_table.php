<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            /*
             * Période de pointage du compte, en jours du mois. « Du 1 au 31 »
             * suit le mois civil ; « du 5 au 4 » suit un cycle à cheval sur deux
             * mois, comme les relevés de certaines cartes à débit différé.
             */
            $table->unsignedTinyInteger('period_start_day')->default(1)->after('initial_balance_cents');
            $table->unsignedTinyInteger('period_end_day')->default(31)->after('period_start_day');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['period_start_day', 'period_end_day']);
        });
    }
};
