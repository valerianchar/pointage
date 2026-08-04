<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->string('name');
            /*
             * Capital emprunté et capital restant dû, en centimes. La part
             * remboursée se déduit des deux : elle n'est jamais stockée.
             */
            $table->bigInteger('borrowed_cents');
            $table->bigInteger('remaining_cents');
            $table->bigInteger('monthly_cents');
            $table->timestamps();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
