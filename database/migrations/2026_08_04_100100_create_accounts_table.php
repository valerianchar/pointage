<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('type', 32);
            /*
             * Le solde courant n'est jamais stocké : il vaut toujours
             * initial_balance_cents + somme des opérations. Le solde saisi à la
             * déclaration du compte devient donc ce point de départ, ce qui
             * évite toute dérive entre le solde affiché et les opérations.
             */
            $table->bigInteger('initial_balance_cents')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
