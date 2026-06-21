<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contrats', function (Blueprint $table) {
            // MariaDB utilise l'unique (client_id, type) pour la FK client_id.
            // Il faut un index ordinaire sur client_id AVANT de supprimer l'unique.
            $table->index('client_id', 'client_contrats_client_id_index');
            // Supprimer la contrainte unique (client_id, type)
            // Un client peut avoir plusieurs contrats du même type (ex: deux prévoyances Alptis)
            $table->dropUnique(['client_id', 'type']);

            // Numéro de contrat assureur (source de vérité : bordereaux de commissions)
            $table->string('numero_contrat')->nullable()->after('assureur_id');

            // Libellé produit exact (ex: "SPI-V3", "Select Séniors FC Niveau 4")
            $table->string('produit')->nullable()->after('numero_contrat');
        });
    }

    public function down(): void
    {
        Schema::table('client_contrats', function (Blueprint $table) {
            $table->dropColumn(['numero_contrat', 'produit']);

            // Restaurer la contrainte unique originale, puis supprimer l'index ordinaire
            $table->unique(['client_id', 'type']);
            $table->dropIndex('client_contrats_client_id_index');
        });
    }
};
