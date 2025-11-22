<?php

namespace App\Services;

use App\Models\QuestionnaireRisque;
use Illuminate\Support\Str;

class ScoringService
{
    private const REPONSES_QUIZ = [
        'volatilite_risque_gain' => 'vrai',
        'instruments_tous_cotes' => 'faux',
        'risque_liquidite_signification' => 'vrai',
        'livret_a_rendement_negatif' => 'vrai',
        'assurance_vie_valeur_rachats_uc' => 'faux',
        'assurance_vie_fiscalite_deces' => 'vrai',
        'per_non_rachatable' => 'vrai',
        'per_objectif_revenus_retraite' => 'vrai',
        'compte_titres_ordres_directs' => 'vrai',
        'pea_actions_europeennes' => 'vrai',
        'opc_pas_de_risque' => 'faux',
        'opc_definition_fonds_investissement' => 'vrai',
        'opcvm_actions_plus_risquees' => 'vrai',
        'scpi_revenus_garantis' => 'faux',
        'opci_scpi_capital_non_garanti' => 'vrai',
        'scpi_liquides' => 'faux',
        'obligations_risque_emetteur' => 'vrai',
        'obligations_cotees_liquidite' => 'vrai',
        'obligation_risque_defaut' => 'vrai',
        'parts_sociales_cotees' => 'faux',
        'parts_sociales_dividendes_voix' => 'vrai',
        'fonds_capital_investissement_non_cotes' => 'vrai',
        'fcp_rachetable_apres_dissolution' => 'vrai',
        'fip_fcpi_reduction_impot' => 'vrai',
        'actions_non_cotees_risque_perte' => 'vrai',
        'actions_cotees_rendement_duree' => 'vrai',
        'produits_structures_complexes' => 'vrai',
        'produits_structures_risque_defaut_banque' => 'vrai',
        'etf_fonds_indiciels' => 'vrai',
        'etf_cotes_en_continu' => 'vrai',
        'girardin_fonds_perdus' => 'vrai',
        'girardin_non_residents' => 'vrai',
    ];

    private const COMPORTEMENT_MAPPINGS = [
        'temps_attente_recuperation_valeur' => [
            'moins_1_an' => 5,
            '1_3_ans' => 15,
            '3_5_ans' => 25,
            'plus_5_ans' => 35,
            'plus_3_ans' => 35,
        ],
        'niveau_perte_inquietude' => [
            'tres_vite' => 5,
            'assez_rapidement' => 15,
            'pas_rapidement' => 25,
            'jamais' => 35,
            'perte_5' => 10,
            'perte_20' => 20,
            'pas_inquietude' => 35,
        ],
        'reaction_baisse_25' => [
            'vendre_tout' => 5,
            'vendre_partie' => 15,
            'ne_rien_faire' => 25,
            'acheter_plus' => 35,
        ],
        'attitude_placements' => [
            'tres_prudent' => 5,
            'prudent' => 15,
            'equilibre' => 25,
            'dynamique' => 35,
            'eviter_pertes' => 10,
            'recherche_gains' => 25,
            'equilibre_gains' => 20,
        ],
        'allocation_epargne' => [
            'allocation_securisee' => 10,
            'allocation_equilibree' => 20,
            'allocation_dynamique' => 30,
            'allocation_70_30' => 30,
            'allocation_30_70' => 15,
            'allocation_50_50' => 20,
        ],
        'objectif_placement' => [
            'protection_capital' => 10,
            'risque_modere' => 20,
            'risque_important' => 30,
        ],
        'horizon_investissement' => [
            'court_terme' => 5,
            'moyen_terme' => 20,
            'long_terme' => 35,
        ],
        'impact_baisse_train_vie' => [
            'aucun_impact' => 35,
            'ajustements' => 20,
            'fort_impact' => 5,
        ],
        'perte_supportable' => [
            'aucune_perte' => 5,
            'perte_10' => 15,
            'perte_25' => 25,
            'perte_50' => 30,
            'perte_capital' => 35,
        ],
        'objectif_global' => [
            'protection' => 10,
            'equilibre' => 20,
            'performance' => 30,
            'securitaire' => 10,
            'revenus' => 20,
            'croissance' => 30,
        ],
        'tolerance_risque' => [
            'tres_faible' => 5,
            'faible' => 15,
            'moderee' => 25,
            'moyen' => 25,
            'elevee' => 35,
        ],
        'niveau_connaissance_globale' => [
            'debutant' => 5,
            'intermediaire' => 15,
            'avance' => 25,
            'expert' => 35,
            'neophyte' => 5,
            'moyennement_experimente' => 20,
            'experimente' => 30,
        ],
        'pourcentage_perte_max' => [
            '0_5' => 5,
            '5_10' => 15,
            '10_20' => 25,
            'plus_20' => 35,
        ],
    ];

    public function calculerScoreQuiz(array $quiz): int
    {
        $bonnesReponses = 0;

        foreach (self::REPONSES_QUIZ as $question => $bonneReponse) {
            if (isset($quiz[$question]) && $quiz[$question] === $bonneReponse) {
                $bonnesReponses++;
            }
        }

        return ($bonnesReponses * 100) / count(self::REPONSES_QUIZ);
    }

    public function scorerEtSauvegarder(QuestionnaireRisque $questionnaire, array $data): QuestionnaireRisque
    {
        $financier = $data['financier'] ?? [];
        $connaissances = $data['connaissances'] ?? [];
        $quiz = $data['quiz'] ?? [];

        $scoreComportemental = $this->scorerComportemental($financier);
        $scoreConnaissances = $this->scorerConnaissances($connaissances);
        $scoreQuiz = $this->calculerScoreQuiz($quiz);

        $scoreGlobal = $this->normaliserScoreComportemental($scoreComportemental);

        $questionnaire->update([
            'score_global' => $scoreGlobal,
            'profil_calcule' => $this->determinerProfil($scoreGlobal),
            'recommandation' => $this->genererRecommandation($scoreGlobal),
        ]);

        if (!empty($financier)) {
            $questionnaire->financier()->updateOrCreate(
                ['questionnaire_risque_id' => $questionnaire->id],
                $financier
            );
        }

        if (!empty($connaissances)) {
            $questionnaire->connaissances()->updateOrCreate(
                ['questionnaire_risque_id' => $questionnaire->id],
                $connaissances
            );
        }

        if (!empty($quiz)) {
            $quiz['score_quiz'] = (int) round($scoreQuiz);
            $questionnaire->quiz()->updateOrCreate(
                ['questionnaire_risque_id' => $questionnaire->id],
                $quiz
            );
        }

        return $questionnaire->fresh();
    }

    private function scorerComportemental(array $data): int
    {
        $score = 0;

        foreach (self::COMPORTEMENT_MAPPINGS as $champ => $valeurs) {
            if (!isset($data[$champ])) {
                continue;
            }

            $valeur = $data[$champ];

            if (isset($valeurs[$valeur])) {
                $score += $valeurs[$valeur];
                continue;
            }

            if ($champ === 'pourcentage_perte_max' && is_numeric($valeur)) {
                $percent = (float) $valeur;
                if ($percent <= 5) {
                    $score += 5;
                } elseif ($percent <= 10) {
                    $score += 15;
                } elseif ($percent <= 20) {
                    $score += 25;
                } else {
                    $score += 35;
                }
            }
        }

        return $score;
    }

    private function scorerConnaissances(array $data): int
    {
        $score = 0;
        $produitsConnus = 0;

        $produits = [
            'connaissance_obligations',
            'connaissance_actions',
            'connaissance_fip_fcpi',
            'connaissance_opci_scpi',
            'connaissance_produits_structures',
            'connaissance_monetaires',
            'connaissance_parts_sociales',
            'connaissance_titres_participatifs',
            'connaissance_fps_slp',
            'connaissance_girardin',
        ];

        foreach ($produits as $produit) {
            if (!empty($data[$produit]) && $data[$produit] == true) {
                $produitsConnus++;
            }
        }

        $score = ($produitsConnus * 100) / count($produits);

        return (int) round($score);
    }

    private function determinerProfil(int $score): string
    {
        if ($score < 45) {
            return 'Prudent';
        }

        if ($score <= 75) {
            return 'Modéré';
        }

        return 'Dynamique';
    }

    private function genererRecommandation(int $score): string
    {
        if ($score < 45) {
            $texte = "Votre profil est **Prudent**. Vous privilégiez la sécurité du capital. Nous recommandons des placements à faible volatilité : fonds euros, obligations d'État, livrets réglementés. Diversification limitée sur des supports à risque modéré.";
        } elseif ($score <= 75) {
            $texte = "Votre profil est **Modéré**. Vous acceptez une certaine volatilité pour rechercher du rendement. Nous recommandons une allocation équilibrée : 50-60% fonds sécurisés, 40-50% actions/SCPI/fonds diversifiés. Horizon minimum 5 ans.";
        } else {
            $texte = "Votre profil est **Dynamique**. Vous recherchez la performance et acceptez la volatilité. Nous recommandons une allocation offensive : 60-80% actions/fonds actions/private equity, 20-40% supports moins risqués. Horizon long terme (>8 ans).";
        }

        return Str::ascii($texte);
    }

    private function normaliserScoreComportemental(int $score): int
    {
        $max = 0;
        foreach (self::COMPORTEMENT_MAPPINGS as $valeurs) {
            $max += max($valeurs);
        }

        if ($max === 0) {
            return 0;
        }

        return (int) round(min(1, $score / $max) * 100);
    }
}
