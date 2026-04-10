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
        Schema::create('sante_souhaits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');

            // Contrat existant
            $table->string('contrat_en_place')->nullable();
            $table->decimal('budget_mensuel_maximum', 8, 2)->nullable();

            // Niveaux de souhait (0-10)
            $table->integer('niveau_hospitalisation')->nullable();
            $table->integer('niveau_chambre_particuliere')->nullable();
            $table->integer('niveau_medecin_generaliste')->nullable();
            $table->integer('niveau_analyses_imagerie')->nullable();
            $table->integer('niveau_auxiliaires_medicaux')->nullable();
            $table->integer('niveau_pharmacie')->nullable();
            $table->integer('niveau_dentaire')->nullable();
            $table->integer('niveau_optique')->nullable();
            $table->integer('niveau_protheses_auditives')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sante_souhaits');
    }
};
