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
        Schema::create('client_biens_immobiliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('designation')->nullable();
            $table->string('detenteur')->nullable();
            $table->string('forme_propriete')->nullable();
            $table->decimal('valeur_actuelle_estimee', 12, 2)->nullable();
            $table->integer('annee_acquisition')->nullable();
            $table->decimal('valeur_acquisition', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_biens_immobiliers');
    }
};
