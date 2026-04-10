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
        Schema::create('conjoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');

            // Identité
            $table->string('nom');
            $table->string('nom_jeune_fille')->nullable();
            $table->string('prenom');
            $table->date('datedenaissance')->nullable();
            $table->string('lieudenaissance')->nullable();
            $table->string('nationalite')->nullable();

            // Professionnel
            $table->string('profession')->nullable();
            $table->string('chef_entreprise')->nullable();
            $table->string('situation_actuelle_statut')->nullable();
            $table->date('date_evenement_professionnel')->nullable();
            $table->boolean('risques_professionnels')->default(false);
            $table->text('details_risques_professionnels')->nullable();

            // Coordonnées
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conjoints');
    }
};
