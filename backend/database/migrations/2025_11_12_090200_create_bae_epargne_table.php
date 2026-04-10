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
        Schema::create('bae_epargne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');

            $table->boolean('epargne_disponible')->nullable();
            $table->decimal('montant_epargne_disponible', 15, 2)->nullable();
            $table->boolean('donation_realisee')->nullable();
            $table->string('donation_forme')->nullable();
            $table->date('donation_date')->nullable();
            $table->decimal('donation_montant', 15, 2)->nullable();
            $table->string('donation_beneficiaires')->nullable();
            $table->decimal('capacite_epargne_estimee', 15, 2)->nullable();

            $table->decimal('actifs_financiers_pourcentage', 5, 2)->nullable();
            $table->decimal('actifs_financiers_total', 15, 2)->nullable();
            $table->json('actifs_financiers_details')->nullable();

            $table->decimal('actifs_immo_pourcentage', 5, 2)->nullable();
            $table->decimal('actifs_immo_total', 15, 2)->nullable();
            $table->json('actifs_immo_details')->nullable();

            $table->decimal('actifs_autres_pourcentage', 5, 2)->nullable();
            $table->decimal('actifs_autres_total', 15, 2)->nullable();
            $table->json('actifs_autres_details')->nullable();

            $table->decimal('passifs_total_emprunts', 15, 2)->nullable();
            $table->json('passifs_details')->nullable();

            $table->decimal('charges_totales', 15, 2)->nullable();
            $table->json('charges_details')->nullable();

            $table->text('situation_financiere_revenus_charges')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bae_epargne');
    }
};
