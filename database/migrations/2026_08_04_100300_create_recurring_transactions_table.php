<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('tag_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->bigInteger('amount_cents');
            // Jour du mois auquel l'instance est générée (1 par défaut).
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
