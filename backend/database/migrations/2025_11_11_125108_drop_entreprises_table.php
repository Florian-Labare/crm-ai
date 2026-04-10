<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('entreprises');
    }

    public function down(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->boolean('chef_entreprise')->default(false);
            $table->string('statut')->nullable();
            $table->boolean('travailleur_independant')->default(false);
            $table->boolean('mandataire_social')->default(false);
            $table->timestamps();
        });
    }
};
