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
        Schema::table('productions', function (Blueprint $table) {
            // Mois du bordereau source (YYYY-MM-01) — permet la détection de résiliation
            $table->date('bordereau_mois')->nullable()->after('date_commission');
            // Format du bordereau source (alptis_cot, selencia, etc.)
            $table->string('bordereau_format', 50)->nullable()->after('bordereau_mois');
            // Numéro adhérent/contrat assureur tel qu'il figure dans le bordereau
            $table->string('numero_adherent', 50)->nullable()->after('bordereau_format');

            $table->index(['team_id', 'bordereau_mois']);
            $table->index(['team_id', 'numero_adherent', 'assureur_id']);
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'bordereau_mois']);
            $table->dropIndex(['team_id', 'numero_adherent', 'assureur_id']);
            $table->dropColumn(['bordereau_mois', 'bordereau_format', 'numero_adherent']);
        });
    }
};
