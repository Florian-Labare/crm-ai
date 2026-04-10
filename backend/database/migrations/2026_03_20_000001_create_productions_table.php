<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nom_client')->nullable();
            $table->string('prenom_client')->nullable();
            $table->foreignId('assureur_id')->nullable()->constrained()->nullOnDelete();
            $table->string('compagnie_libre')->nullable();
            $table->string('categorie')->nullable();
            $table->string('type_contrat')->nullable();
            $table->year('annee')->nullable();
            $table->date('date_signature')->nullable();
            $table->date('date_effet')->nullable();
            $table->decimal('prime_ttc', 12, 2)->nullable();
            $table->decimal('prime_ht', 12, 2)->nullable();
            $table->decimal('fond_euro', 12, 2)->nullable();
            $table->decimal('uc', 12, 2)->nullable();
            $table->decimal('taux_commission', 6, 4)->nullable();
            $table->decimal('commission_compagnie', 12, 2)->nullable();
            $table->decimal('commission_mia', 12, 2)->nullable();
            $table->decimal('commission_recurrente', 12, 2)->nullable();
            $table->decimal('encours_commission', 12, 2)->nullable();
            $table->date('date_commission')->nullable();
            $table->boolean('regul_transmise')->default(false);
            $table->boolean('regul_signee')->default(false);
            $table->date('date_resiliation')->nullable();
            $table->date('date_reprise')->nullable();
            $table->enum('statut', ['active', 'resilie', 'attente', 'frigo'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
            $table->index(['team_id', 'annee']);
            $table->index(['team_id', 'statut']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('productions');
    }
};
