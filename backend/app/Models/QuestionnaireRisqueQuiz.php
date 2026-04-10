<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireRisqueQuiz extends Model
{
    protected $fillable = [
        'questionnaire_risque_id',
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
        'score_quiz',
    ];

    protected $casts = [
        'score_quiz' => 'integer',
    ];

    public function questionnaireRisque(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireRisque::class);
    }
}
