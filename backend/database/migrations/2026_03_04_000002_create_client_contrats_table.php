<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->enum('type', ['sante', 'prevoyance', 'per', 'assurance_vie', 'emprunteur', 'vie_entiere']);
            $table->foreignId('assureur_id')->nullable()->constrained('assureurs')->onDelete('set null');
            $table->decimal('mensualite', 12, 2)->nullable();
            $table->decimal('en_cours', 15, 2)->nullable();
            $table->decimal('fond_euro', 15, 2)->nullable();
            $table->decimal('uc', 15, 2)->nullable();
            $table->decimal('versement_programme', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contrats');
    }
};
