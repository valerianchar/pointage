<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Membres d'un compte joint : le propriétaire invite un utilisateur, qui
     * accepte — ou pas. Tant que accepted_at est vide, l'invitation est en
     * attente et n'ouvre aucun accès. Comme partout, pas de cascade : la
     * suppression d'un compte purge ses membres explicitement.
     */
    public function up(): void
    {
        Schema::create('account_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_members');
    }
};
