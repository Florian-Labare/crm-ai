<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_passifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('nature')->nullable();
            $table->string('preteur')->nullable();
            $table->string('periodicite')->nullable();
            $table->decimal('montant_remboursement', 12, 2)->nullable();
            $table->decimal('capital_restant_du', 12, 2)->nullable();
            $table->integer('duree_restante')->nullable(); // en mois
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_passifs');
    }
};
