<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajout de 23 nouveaux champs pour supporter la migration complète des templates documentaires
     * - clients: 3 champs
     * - conjoints: 8 champs
     * - bae_prevoyance: 6 champs
     * - bae_retraite: 1 champ
     * - sante_souhaits: 5 champs
     */
    public function up(): void
    {
        // Table: clients
        Schema::table('clients', function (Blueprint $table) {
            $table->text('situation_professionnelle')->nullable()->after('profession');
            $table->integer('km_parcourus_annuels')->nullable()->after('niveau_activites_sportives');
            $table->string('genre', 1)->nullable()->after('prenom');
        });

        // Table: conjoints
        Schema::table('conjoints', function (Blueprint $table) {
            $table->boolean('travailleur_independant')->nullable()->after('chef_entreprise');
            $table->text('situation_professionnelle')->nullable()->after('profession');
            $table->string('statut')->nullable()->after('situation_professionnelle');
            $table->text('niveau_activite_sportive')->nullable()->after('situation_actuelle_statut');
            $table->text('details_activites_sportives')->nullable()->after('niveau_activite_sportive');
            $table->boolean('situation_chomage')->nullable()->after('situation_professionnelle');
            $table->string('code_postal', 10)->nullable()->after('adresse');
            $table->string('ville')->nullable()->after('code_postal');
        });

        // Table: bae_prevoyance
        Schema::table('bae_prevoyance', function (Blueprint $table) {
            $table->text('deplacements_professionnels')->nullable()->after('payeur');
            $table->text('deplacements_professionnels_conjoint')->nullable()->after('deplacements_professionnels');
            $table->string('duree_indemnisation_frais_pro')->nullable()->after('duree_indemnisation_souhaitee');
            $table->string('denomination_contrat')->nullable()->after('contrat_en_place');
            $table->decimal('montant_garanti', 12, 2)->nullable()->after('capital_deces_souhaite');
            $table->boolean('souhaite_garantie_outillage')->nullable()->after('souhaite_couvrir_charges_professionnelles');
        });

        // Table: bae_retraite
        Schema::table('bae_retraite', function (Blueprint $table) {
            $table->date('date_evenement_retraite')->nullable()->after('age_depart_retraite');
        });

        // Table: sante_souhaits
        Schema::table('sante_souhaits', function (Blueprint $table) {
            $table->boolean('souhaite_medecine_douce')->nullable()->after('niveau_protheses_auditives');
            $table->boolean('souhaite_cures_thermales')->nullable()->after('souhaite_medecine_douce');
            $table->boolean('souhaite_autres_protheses')->nullable()->after('souhaite_cures_thermales');
            $table->boolean('souhaite_protection_juridique')->nullable()->after('souhaite_autres_protheses');
            $table->boolean('souhaite_protection_juridique_conjoint')->nullable()->after('souhaite_protection_juridique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['situation_professionnelle', 'km_parcourus_annuels', 'genre']);
        });

        Schema::table('conjoints', function (Blueprint $table) {
            $table->dropColumn([
                'travailleur_independant',
                'situation_professionnelle',
                'statut',
                'niveau_activite_sportive',
                'details_activites_sportives',
                'situation_chomage',
                'code_postal',
                'ville',
            ]);
        });

        Schema::table('bae_prevoyance', function (Blueprint $table) {
            $table->dropColumn([
                'deplacements_professionnels',
                'deplacements_professionnels_conjoint',
                'duree_indemnisation_frais_pro',
                'denomination_contrat',
                'montant_garanti',
                'souhaite_garantie_outillage',
            ]);
        });

        Schema::table('bae_retraite', function (Blueprint $table) {
            $table->dropColumn('date_evenement_retraite');
        });

        Schema::table('sante_souhaits', function (Blueprint $table) {
            $table->dropColumn([
                'souhaite_medecine_douce',
                'souhaite_cures_thermales',
                'souhaite_autres_protheses',
                'souhaite_protection_juridique',
                'souhaite_protection_juridique_conjoint',
            ]);
        });
    }
};
