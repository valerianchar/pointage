<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Un compte partagé ne se supprime pas d'un geste unilatéral : n'importe
     * quel membre peut le demander, et chaque autre membre doit donner son
     * accord. L'unanimité supprime, un seul refus annule. Une seule demande
     * à la fois par compte.
     */
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('account_deletion_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_deletion_request_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->unique(['account_deletion_request_id', 'user_id'], 'deletion_approval_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_approvals');
        Schema::dropIfExists('account_deletion_requests');
    }
};
