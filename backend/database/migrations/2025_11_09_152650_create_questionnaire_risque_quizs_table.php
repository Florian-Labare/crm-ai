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
        if (Schema::hasTable('questionnaire_risque_quizs') && !Schema::hasTable('questionnaire_risque_quizzes')) {
            Schema::rename('questionnaire_risque_quizs', 'questionnaire_risque_quizzes');
        }

        if (!Schema::hasTable('questionnaire_risque_quizzes')) {
            Schema::create('questionnaire_risque_quizzes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('questionnaire_risque_id')
                    ->constrained('questionnaire_risques')
                    ->onDelete('cascade');

                $questions = [
                    'volatilite_risque_gain',
                    'instruments_tous_cotes',
                    'risque_liquidite_signification',
                    'livret_a_rendement_negatif',
                    'assurance_vie_valeur_rachats_uc',
                    'assurance_vie_fiscalite_deces',
                    'per_non_rachatable',
                    'per_objectif_revenus_retraite',
                    'compte_titres_ordres_directs',
                    'pea_actions_europeennes',
                    'opc_pas_de_risque',
                    'opc_definition_fonds_investissement',
                    'opcvm_actions_plus_risquees',
                    'scpi_revenus_garantis',
                    'opci_scpi_capital_non_garanti',
                    'scpi_liquides',
                    'obligations_risque_emetteur',
                    'obligations_cotees_liquidite',
                    'obligation_risque_defaut',
                    'parts_sociales_cotees',
                    'parts_sociales_dividendes_voix',
                    'fonds_capital_investissement_non_cotes',
                    'fcp_rachetable_apres_dissolution',
                    'fip_fcpi_reduction_impot',
                    'actions_non_cotees_risque_perte',
                    'actions_cotees_rendement_duree',
                    'produits_structures_complexes',
                    'produits_structures_risque_defaut_banque',
                    'etf_fonds_indiciels',
                    'etf_cotes_en_continu',
                    'girardin_fonds_perdus',
                    'girardin_non_residents',
                ];

                foreach ($questions as $column) {
                    $table->string($column)->nullable();
                }

                $table->integer('score_quiz')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('questionnaire_risque_quizs')) {
            Schema::dropIfExists('questionnaire_risque_quizs');
        }

        Schema::dropIfExists('questionnaire_risque_quizzes');
    }
};
