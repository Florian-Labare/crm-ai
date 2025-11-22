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
        Schema::create('bae_prevoyance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');

            $table->string('contrat_en_place')->nullable();
            $table->date('date_effet')->nullable();
            $table->decimal('cotisations', 15, 2)->nullable();
            $table->boolean('souhaite_couverture_invalidite')->nullable();
            $table->decimal('revenu_a_garantir', 15, 2)->nullable();
            $table->boolean('souhaite_couvrir_charges_professionnelles')->nullable();
            $table->decimal('montant_annuel_charges_professionnelles', 15, 2)->nullable();
            $table->boolean('garantir_totalite_charges_professionnelles')->nullable();
            $table->decimal('montant_charges_professionnelles_a_garantir', 15, 2)->nullable();
            $table->string('duree_indemnisation_souhaitee')->nullable();
            $table->decimal('capital_deces_souhaite', 15, 2)->nullable();
            $table->decimal('garanties_obseques', 15, 2)->nullable();
            $table->decimal('rente_enfants', 15, 2)->nullable();
            $table->decimal('rente_conjoint', 15, 2)->nullable();
            $table->string('payeur')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bae_prevoyance');
    }
};
