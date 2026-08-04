<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('tag_id')->nullable()->constrained()->nullOnDelete();
            // Renseigné quand l'opération est l'instance mensuelle d'un modèle récurrent.
            $table->foreignId('recurring_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            /*
             * Montant signé, en centimes : négatif pour une dépense, positif pour
             * un ajout. Des entiers gardent les sommes exactes, sans arrondi flottant.
             */
            $table->bigInteger('amount_cents');
            $table->date('occurred_on');
            // Date de rapprochement avec le relevé bancaire ; nul = « à pointer ».
            $table->timestamp('pointed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'occurred_on']);
            $table->index(['account_id', 'pointed_at']);
            // Garantit l'idempotence de la génération mensuelle des récurrentes.
            $table->unique(['recurring_transaction_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
