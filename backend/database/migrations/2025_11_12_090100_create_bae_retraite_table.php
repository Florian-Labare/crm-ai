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
        Schema::create('bae_retraite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');

            $table->decimal('revenus_annuels', 15, 2)->nullable();
            $table->decimal('revenus_annuels_foyer', 15, 2)->nullable();
            $table->decimal('impot_revenu', 15, 2)->nullable();
            $table->decimal('nombre_parts_fiscales', 4, 2)->nullable();
            $table->string('tmi')->nullable();
            $table->decimal('impot_paye_n_1', 15, 2)->nullable();
            $table->unsignedTinyInteger('age_depart_retraite')->nullable();
            $table->unsignedTinyInteger('age_depart_retraite_conjoint')->nullable();
            $table->decimal('pourcentage_revenu_a_maintenir', 5, 2)->nullable();
            $table->string('contrat_en_place')->nullable();
            $table->boolean('bilan_retraite_disponible')->nullable();
            $table->boolean('complementaire_retraite_mise_en_place')->nullable();
            $table->string('designation_etablissement')->nullable();
            $table->decimal('cotisations_annuelles', 15, 2)->nullable();
            $table->string('titulaire')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bae_retraite');
    }
};
