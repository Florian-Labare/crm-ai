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
        // 1. Table principale : questionnaire_risques
        Schema::table('questionnaire_risques', function (Blueprint $table) {
            if (!Schema::hasColumn('questionnaire_risques', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('id')->constrained('clients')->onDelete('cascade');
            }
            if (!Schema::hasColumn('questionnaire_risques', 'score_global')) {
                $table->integer('score_global')->default(0)->after('client_id');
            }
            if (!Schema::hasColumn('questionnaire_risques', 'profil_calcule')) {
                $table->string('profil_calcule')->default('Prudent')->after('score_global');
            }
            if (!Schema::hasColumn('questionnaire_risques', 'recommandation')) {
                $table->text('recommandation')->nullable()->after('profil_calcule');
            }
        });

        // 2. Table questionnaire_risque_financiers
        if (!Schema::hasColumn('questionnaire_risque_financiers', 'questionnaire_risque_id')) {
            Schema::table('questionnaire_risque_financiers', function (Blueprint $table) {
                $table->unsignedBigInteger('questionnaire_risque_id')->nullable()->after('id');
                $table->foreign('questionnaire_risque_id', 'qr_financier_qr_id_fk')
                    ->references('id')->on('questionnaire_risques')->onDelete('cascade');

                // Champs comportementaux (questions financières)
                $table->string('temps_attente_recuperation_valeur')->nullable()->after('questionnaire_risque_id');
                $table->string('niveau_perte_inquietude')->nullable()->after('temps_attente_recuperation_valeur');
                $table->string('reaction_baisse_25')->nullable()->after('niveau_perte_inquietude');
                $table->string('attitude_placements')->nullable()->after('reaction_baisse_25');
                $table->string('allocation_epargne')->nullable()->after('attitude_placements');
                $table->string('objectif_placement')->nullable()->after('allocation_epargne');
                $table->boolean('placements_inquietude')->default(false)->after('objectif_placement');
                $table->boolean('epargne_precaution')->default(false)->after('placements_inquietude');
                $table->string('reaction_moins_value')->nullable()->after('epargne_precaution');
                $table->string('impact_baisse_train_vie')->nullable()->after('reaction_moins_value');
                $table->string('perte_supportable')->nullable()->after('impact_baisse_train_vie');
                $table->string('objectif_global')->nullable()->after('perte_supportable');
                $table->string('horizon_investissement')->nullable()->after('objectif_global');
                $table->string('tolerance_risque')->nullable()->after('horizon_investissement');
                $table->string('niveau_connaissance_globale')->nullable()->after('tolerance_risque');
                $table->string('pourcentage_perte_max')->nullable()->after('niveau_connaissance_globale');
            });
        }

        // 3. Table questionnaire_risque_connaissances
        if (!Schema::hasColumn('questionnaire_risque_connaissances', 'questionnaire_risque_id')) {
            Schema::table('questionnaire_risque_connaissances', function (Blueprint $table) {
                $table->unsignedBigInteger('questionnaire_risque_id')->nullable()->after('id');
                $table->foreign('questionnaire_risque_id', 'qr_connaissance_qr_id_fk')
                    ->references('id')->on('questionnaire_risques')->onDelete('cascade');

                // Obligations
                $table->boolean('connaissance_obligations')->default(false)->after('questionnaire_risque_id');
                $table->decimal('montant_obligations', 15, 2)->nullable()->after('connaissance_obligations');

                // Actions
                $table->boolean('connaissance_actions')->default(false)->after('montant_obligations');
                $table->decimal('montant_actions', 15, 2)->nullable()->after('connaissance_actions');

                // FIP/FCPI
                $table->boolean('connaissance_fip_fcpi')->default(false)->after('montant_actions');
                $table->decimal('montant_fip_fcpi', 15, 2)->nullable()->after('connaissance_fip_fcpi');

                // OPCI/SCPI
                $table->boolean('connaissance_opci_scpi')->default(false)->after('montant_fip_fcpi');
                $table->decimal('montant_opci_scpi', 15, 2)->nullable()->after('connaissance_opci_scpi');

                // Produits structurés
                $table->boolean('connaissance_produits_structures')->default(false)->after('montant_opci_scpi');
                $table->decimal('montant_produits_structures', 15, 2)->nullable()->after('connaissance_produits_structures');

                // Monétaires
                $table->boolean('connaissance_monetaires')->default(false)->after('montant_produits_structures');
                $table->decimal('montant_monetaires', 15, 2)->nullable()->after('connaissance_monetaires');

                // Parts sociales
                $table->boolean('connaissance_parts_sociales')->default(false)->after('montant_monetaires');
                $table->decimal('montant_parts_sociales', 15, 2)->nullable()->after('connaissance_parts_sociales');

                // Titres participatifs
                $table->boolean('connaissance_titres_participatifs')->default(false)->after('montant_parts_sociales');
                $table->decimal('montant_titres_participatifs', 15, 2)->nullable()->after('connaissance_titres_participatifs');

                // FPS/SLP
                $table->boolean('connaissance_fps_slp')->default(false)->after('montant_titres_participatifs');
                $table->decimal('montant_fps_slp', 15, 2)->nullable()->after('connaissance_fps_slp');

                // Girardin
                $table->boolean('connaissance_girardin')->default(false)->after('montant_fps_slp');
                $table->decimal('montant_girardin', 15, 2)->nullable()->after('connaissance_girardin');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les colonnes de questionnaire_risques
        Schema::table('questionnaire_risques', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn([
                'client_id',
                'score_global',
                'profil_calcule',
                'recommandation',
            ]);
        });

        // Supprimer les colonnes de questionnaire_risque_financiers
        Schema::table('questionnaire_risque_financiers', function (Blueprint $table) {
            $table->dropForeign('qr_financier_qr_id_fk');
            $table->dropColumn([
                'questionnaire_risque_id',
                'temps_attente_recuperation_valeur',
                'niveau_perte_inquietude',
                'reaction_baisse_25',
                'attitude_placements',
                'allocation_epargne',
                'objectif_placement',
                'placements_inquietude',
                'epargne_precaution',
                'reaction_moins_value',
                'impact_baisse_train_vie',
                'perte_supportable',
                'objectif_global',
                'horizon_investissement',
                'tolerance_risque',
                'niveau_connaissance_globale',
                'pourcentage_perte_max',
            ]);
        });

        // Supprimer les colonnes de questionnaire_risque_connaissances
        Schema::table('questionnaire_risque_connaissances', function (Blueprint $table) {
            $table->dropForeign('qr_connaissance_qr_id_fk');
            $table->dropColumn([
                'questionnaire_risque_id',
                'connaissance_obligations',
                'montant_obligations',
                'connaissance_actions',
                'montant_actions',
                'connaissance_fip_fcpi',
                'montant_fip_fcpi',
                'connaissance_opci_scpi',
                'montant_opci_scpi',
                'connaissance_produits_structures',
                'montant_produits_structures',
                'connaissance_monetaires',
                'montant_monetaires',
                'connaissance_parts_sociales',
                'montant_parts_sociales',
                'connaissance_titres_participatifs',
                'montant_titres_participatifs',
                'connaissance_fps_slp',
                'montant_fps_slp',
                'connaissance_girardin',
                'montant_girardin',
            ]);
        });
    }
};
