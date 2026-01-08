<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\QuestionnaireRisque;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class ExportController extends Controller
{
    /**
     * Exporter la fiche client en PDF
     */
    public function exportPdf($id)
    {
        $client = Client::with(['conjoint', 'enfants', 'santeSouhait'])->findOrFail($id);

        $pdf = Pdf::loadView('exports.client-pdf', [
            'client' => $client
        ]);

        $filename = 'fiche_client_' . $client->nom . '_' . $client->prenom . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Exporter la fiche client en Word
     */
    public function exportWord($id)
    {
        $client = Client::with(['conjoint', 'enfants', 'santeSouhait'])->findOrFail($id);

        $phpWord = new PhpWord();

        // Configuration du document
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        // Création de la section
        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        // Styles
        $titleStyle = ['bold' => true, 'size' => 16, 'color' => '4F46E5'];
        $heading1Style = ['bold' => true, 'size' => 14, 'color' => '312E81'];
        $heading2Style = ['bold' => true, 'size' => 12, 'color' => '6366F1'];
        $labelStyle = ['bold' => true, 'size' => 11, 'color' => '374151'];
        $textStyle = ['size' => 11];

        // Titre du document
        $section->addText(
            '🎧 WHISPER CRM - FICHE CLIENT',
            $titleStyle,
            ['alignment' => Jc::CENTER, 'spaceAfter' => 300]
        );

        // Identité
        $section->addText('IDENTITÉ', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Civilité', $client->civilite, $labelStyle, $textStyle);
        $this->addField($section, 'Nom', $client->nom, $labelStyle, $textStyle);
        if ($client->nom_jeune_fille) {
            $this->addField($section, 'Nom de jeune fille', $client->nom_jeune_fille, $labelStyle, $textStyle);
        }
        $this->addField($section, 'Prénom', $client->prenom, $labelStyle, $textStyle);
        $this->addField($section, 'Date de naissance', $client->date_naissance ? \Carbon\Carbon::parse($client->date_naissance)->format('d/m/Y') : null, $labelStyle, $textStyle);
        $this->addField($section, 'Lieu de naissance', $client->lieu_naissance, $labelStyle, $textStyle);
        $this->addField($section, 'Nationalité', $client->nationalite, $labelStyle, $textStyle);

        // Coordonnées
        $section->addText('COORDONNÉES', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Adresse', $client->adresse, $labelStyle, $textStyle);
        $this->addField($section, 'Code postal', $client->code_postal, $labelStyle, $textStyle);
        $this->addField($section, 'Ville', $client->ville, $labelStyle, $textStyle);
        $this->addField($section, 'Résidence fiscale', $client->residence_fiscale, $labelStyle, $textStyle);
        $this->addField($section, 'Téléphone', $client->telephone, $labelStyle, $textStyle);
        $this->addField($section, 'Email', $client->email, $labelStyle, $textStyle);

        // Situation
        $section->addText('SITUATION PERSONNELLE', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Situation matrimoniale', $client->situation_matrimoniale, $labelStyle, $textStyle);
        $this->addField($section, 'Date situation matrimoniale', $client->date_situation_matrimoniale ? \Carbon\Carbon::parse($client->date_situation_matrimoniale)->format('d/m/Y') : null, $labelStyle, $textStyle);
        $this->addField($section, 'Situation actuelle', $client->situation_actuelle, $labelStyle, $textStyle);
        $this->addField($section, 'Nombre d\'enfants', $client->nombre_enfants, $labelStyle, $textStyle);

        // Conjoint
        if ($client->conjoint) {
            $section->addText('CONJOINT', $heading2Style, ['spaceAfter' => 200, 'spaceBefore' => 300]);
            $this->addField($section, 'Nom', $client->conjoint->nom, $labelStyle, $textStyle);
            $this->addField($section, 'Prénom', $client->conjoint->prenom, $labelStyle, $textStyle);
            $this->addField($section, 'Date de naissance', $client->conjoint->date_naissance ? \Carbon\Carbon::parse($client->conjoint->date_naissance)->format('d/m/Y') : null, $labelStyle, $textStyle);
        }

        // Enfants
        if ($client->enfants && $client->enfants->count() > 0) {
            $section->addText('ENFANTS', $heading2Style, ['spaceAfter' => 200, 'spaceBefore' => 300]);
            foreach ($client->enfants as $index => $enfant) {
                $section->addText('Enfant ' . ($index + 1), ['bold' => true, 'size' => 11], ['spaceAfter' => 100]);
                $this->addField($section, 'Prénom', $enfant->prenom, $labelStyle, $textStyle);
                $this->addField($section, 'Date de naissance', $enfant->date_naissance ? \Carbon\Carbon::parse($enfant->date_naissance)->format('d/m/Y') : null, $labelStyle, $textStyle);
            }
        }

        // Professionnel
        $section->addText('SITUATION PROFESSIONNELLE', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Profession', $client->profession, $labelStyle, $textStyle);
        $this->addField($section, 'Date événement professionnel', $client->date_evenement_professionnel ? \Carbon\Carbon::parse($client->date_evenement_professionnel)->format('d/m/Y') : null, $labelStyle, $textStyle);
        $this->addField($section, 'Revenus annuels', $client->revenus_annuels ? number_format($client->revenus_annuels, 0, ',', ' ') . ' €' : null, $labelStyle, $textStyle);
        $this->addField($section, 'Risques professionnels', $client->risques_professionnels ? 'Oui' : 'Non', $labelStyle, $textStyle);
        $this->addField($section, 'Détails risques', $client->details_risques_professionnels, $labelStyle, $textStyle);
        $this->addField($section, 'Charge clientèle', $client->charge_clientele, $labelStyle, $textStyle);

        // Entreprise
        if ($client->entreprise) {
            $section->addText('ENTREPRISE', $heading2Style, ['spaceAfter' => 200, 'spaceBefore' => 300]);
            $this->addField($section, 'Nom', $client->entreprise->nom, $labelStyle, $textStyle);
            $this->addField($section, 'Forme juridique', $client->entreprise->forme_juridique, $labelStyle, $textStyle);
            $this->addField($section, 'SIRET', $client->entreprise->siret, $labelStyle, $textStyle);
        }

        // Mode de vie
        $section->addText('MODE DE VIE', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Fumeur', $client->fumeur ? 'Oui' : 'Non', $labelStyle, $textStyle);
        $this->addField($section, 'Activités sportives', $client->activites_sportives ? 'Oui' : 'Non', $labelStyle, $textStyle);
        $this->addField($section, 'Détails activités', $client->details_activites_sportives, $labelStyle, $textStyle);
        $this->addField($section, 'Niveau activités', $client->niveau_activites_sportives, $labelStyle, $textStyle);

        // Besoins
        if ($client->besoins && is_array($client->besoins) && count($client->besoins) > 0) {
            $section->addText('BESOINS IDENTIFIÉS', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
            foreach ($client->besoins as $besoin) {
                $section->addListItem($besoin, 0, null, ['spaceAfter' => 100]);
            }
        }

        // Santé et souhaits
        if ($client->santeSouhait) {
            $section->addText('SANTÉ ET SOUHAITS', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
            $sante = $client->santeSouhait;
            if ($sante->contrat_en_place) {
                $this->addField($section, 'Contrat en place', $sante->contrat_en_place, $labelStyle, $textStyle);
            }
            if ($sante->budget_mensuel_maximum) {
                $this->addField($section, 'Budget mensuel maximum', $sante->budget_mensuel_maximum . ' €', $labelStyle, $textStyle);
            }
        }

        // Notes
        if ($client->notes) {
            $section->addText('NOTES', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
            $section->addText($client->notes, $textStyle);
        }

        // Pied de page
        $section->addText(
            '_______________________________________________',
            ['size' => 10, 'color' => 'CCCCCC'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 100, 'spaceBefore' => 400]
        );
        $section->addText(
            'Document généré le ' . now()->format('d/m/Y à H:i') . ' par Whisper CRM',
            ['size' => 9, 'color' => '6B7280'],
            ['alignment' => Jc::CENTER]
        );

        // Génération du fichier
        $filename = 'fiche_client_' . $client->nom . '_' . $client->prenom . '.docx';
        $tempFile = storage_path('app/temp/' . $filename);

        // Créer le dossier temp s'il n'existe pas
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Exporter le questionnaire de risque en PDF
     */
    public function exportQuestionnairePdf($id)
    {
        $client = Client::findOrFail($id);
        $questionnaire = QuestionnaireRisque::with(['financier', 'connaissances', 'quiz'])
            ->where('client_id', $id)
            ->first();

        if (!$questionnaire) {
            abort(404, 'Questionnaire introuvable pour ce client.');
        }

        $pdf = Pdf::loadView('exports.questionnaire-pdf', [
            'client' => $client,
            'questionnaire' => $questionnaire,
            'financierResponses' => $this->formatFinancierResponses($questionnaire->financier),
            'connaissanceResponses' => $this->formatConnaissanceResponses($questionnaire->connaissances),
            'quizResponses' => $this->formatQuizResponses($questionnaire->quiz),
        ]);

        $filename = 'questionnaire_client_' . $client->nom . '_' . $client->prenom . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Ajouter un champ au document Word
     */
    private function addField($section, $label, $value, $labelStyle, $textStyle)
    {
        if ($value !== null && $value !== '') {
            $section->addText($label . ' : ' . $value, array_merge($textStyle, $labelStyle), ['spaceAfter' => 100]);
        }
    }

    private function formatFinancierResponses($financier): array
    {
        if (!$financier) {
            return [];
        }

        $questions = [
            'temps_attente_recuperation_valeur' => [
                'label' => 'La valeur de votre investissement baisse, combien de temps êtes-vous disposé à attendre ?',
                'options' => [
                    'moins_1_an' => "Moins d'1 an",
                    '1_3_ans' => "Entre 1 et 3 ans",
                    '3_5_ans' => "Entre 3 et 5 ans",
                    'plus_5_ans' => "Plus de 5 ans",
                    'plus_3_ans' => "Plus de 3 ans",
                ],
            ],
            'niveau_perte_inquietude' => [
                'label' => 'À partir de quel niveau de perte êtes-vous vraiment inquiet ?',
                'options' => [
                    'perte_5' => '5 %',
                    'perte_20' => '20 %',
                    'pas_inquietude' => 'Je sais que cela peut remonter donc pas d’inquiétude',
                    'tres_vite' => 'Très vite (dès 5%)',
                    'assez_rapidement' => 'Assez rapidement (10-15%)',
                    'pas_rapidement' => 'Pas rapidement (15-25%)',
                    'jamais' => 'Jamais inquiet',
                ],
            ],
            'reaction_baisse_25' => [
                'label' => 'La Bourse dégringole et vos actions perdent 25 % : que faites-vous ?',
                'options' => [
                    'vendre_partie' => 'J’hésite peut-être à vendre une partie',
                    'acheter_plus' => 'J’achète plus de ces actions',
                    'vendre_tout' => 'Je vends tout sans attendre',
                    'ne_rien_faire' => 'Ne rien faire, attendre',
                ],
            ],
            'attitude_placements' => [
                'label' => 'Quelle affirmation vous convient le mieux s’agissant de vos placements ?',
                'options' => [
                    'eviter_pertes' => 'Je redoute avant tout les pertes',
                    'recherche_gains' => 'Je m’intéresse surtout aux gains',
                    'equilibre_gains' => 'Je m’intéresse aux deux',
                    'tres_prudent' => 'Très prudent',
                    'prudent' => 'Prudent',
                    'equilibre' => 'Équilibré',
                    'dynamique' => 'Dynamique',
                ],
            ],
            'allocation_epargne' => [
                'label' => 'Quelle allocation de votre épargne vous convient le mieux ?',
                'options' => [
                    'allocation_70_30' => '70 % actifs de croissance / 30 % actifs défensifs',
                    'allocation_30_70' => '30 % actifs de croissance / 70 % actifs défensifs',
                    'allocation_50_50' => '50 % actifs de croissance / 50 % actifs défensifs',
                    'allocation_securisee' => '80 % sécurisé / 20 % risqué',
                    'allocation_equilibree' => '50 % sécurisé / 50 % risqué',
                    'allocation_dynamique' => '20 % sécurisé / 80 % risqué',
                ],
            ],
            'objectif_placement' => [
                'label' => 'Quelle affirmation vous correspond le mieux (épargne) ?',
                'options' => [
                    'protection_capital' => 'La protection du capital est ma priorité',
                    'risque_modere' => 'Je suis prêt à prendre des risques modérés pour viser de meilleurs rendements',
                    'risque_important' => 'Je suis prêt à prendre des risques importants en contrepartie d’une espérance de gain élevé',
                ],
            ],
            'placements_inquietude' => [
                'label' => 'Vos placements financiers sont-ils une source d’inquiétude ?',
                'type' => 'boolean',
            ],
            'epargne_precaution' => [
                'label' => 'Besoin de constituer une épargne de précaution ?',
                'type' => 'boolean',
            ],
            'reaction_moins_value' => [
                'label' => 'Vous constatez une moins-value, votre réaction ?',
                'options' => [
                    'contacter_immediat' => 'Vous appelez tout de suite votre conseiller',
                    'voir_plus_tard' => 'Vous poserez la question à votre conseiller la prochaine fois que vous le verrez',
                ],
            ],
            'impact_baisse_train_vie' => [
                'label' => 'Incidence d’une baisse sur votre train de vie ?',
                'options' => [
                    'aucun_impact' => 'Je ne vis pas de mes placements',
                    'ajustements' => 'Je compte un peu sur mes placements, une baisse impliquerait des ajustements',
                    'fort_impact' => 'Je vis de mes placements, une baisse nuirait à mon train de vie',
                ],
            ],
            'perte_supportable' => [
                'label' => 'Niveau de perte prêt à supporter',
                'options' => [
                    'aucune_perte' => 'Aucune perte',
                    'perte_10' => 'Perte limitée à 10 %',
                    'perte_25' => 'Perte limitée à 25 %',
                    'perte_50' => 'Perte limitée à 50 %',
                    'perte_capital' => 'Perte limitée au capital investi',
                ],
            ],
            'objectifs_rapport' => [
                'label' => 'Le présent rapport répond à',
            ],
            'horizon_investissement' => [
                'label' => 'Horizon d’investissement pour ces objectifs',
                'options' => [
                    'court_terme' => 'Court terme (moins de 3 ans)',
                    'moyen_terme' => 'Moyen terme (3 à 8 ans)',
                    'long_terme' => 'Long terme (plus de 8 ans)',
                ],
            ],
            'objectif_global' => [
                'label' => 'Objectif d’investissement',
                'options' => [
                    'protection' => 'Protection / Sécuritaire',
                    'equilibre' => 'Équilibre / Revenus',
                    'performance' => 'Performance / Croissance',
                    'securitaire' => 'Sécuritaire (préservation du capital)',
                    'revenus' => 'Revenus (dividendes, etc.)',
                    'croissance' => 'Croissance (faire fructifier le capital)',
                ],
            ],
            'tolerance_risque' => [
                'label' => 'Tolérance au risque',
                'options' => [
                    'faible' => 'Faible',
                    'moyen' => 'Moyen',
                    'moderee' => 'Modérée',
                    'elevee' => 'Élevée',
                ],
            ],
            'niveau_connaissance_globale' => [
                'label' => 'Connaissance des instruments financiers',
                'options' => [
                    'neophyte' => 'Néophyte',
                    'debutant' => 'Débutant',
                    'intermediaire' => 'Intermédiaire',
                    'avance' => 'Avancé',
                    'experimente' => 'Expérimenté',
                    'moyennement_experimente' => 'Moyennement expérimenté',
                ],
            ],
            'pourcentage_perte_max' => [
                'label' => 'Pourcentage maximum de pertes',
            ],
        ];

        $responses = [];

        foreach ($questions as $field => $meta) {
            if (!isset($financier->$field) || $financier->$field === null || $financier->$field === '') {
                continue;
            }

            $value = $financier->$field;
            if (($meta['type'] ?? null) === 'boolean') {
                $answer = $value ? 'Oui' : 'Non';
            } elseif (isset($meta['options'][$value])) {
                $answer = $meta['options'][$value];
            } elseif ($field === 'pourcentage_perte_max') {
                $answer = rtrim(rtrim((string)$value, '0'), '.') . ' %';
            } else {
                $answer = $value;
            }

            $responses[] = [
                'question' => $meta['label'],
                'answer' => $answer,
            ];
        }

        return $responses;
    }

    private function formatConnaissanceResponses($connaissances): array
    {
        if (!$connaissances) {
            return [];
        }

        $labels = [
            'connaissance_obligations' => 'Obligations',
            'connaissance_actions' => 'Actions',
            'connaissance_fip_fcpi' => 'FIP / FCPI',
            'connaissance_opci_scpi' => 'OPCI / SCPI',
            'connaissance_produits_structures' => 'Produits structurés',
            'connaissance_monetaires' => 'Fonds monétaires',
            'connaissance_parts_sociales' => 'Parts sociales',
            'connaissance_titres_participatifs' => 'Titres participatifs',
            'connaissance_fps_slp' => 'FPS / SLP',
            'connaissance_girardin' => 'Girardin',
        ];

        $responses = [];

        foreach ($labels as $field => $label) {
            if (!isset($connaissances->$field)) {
                continue;
            }

            $responses[] = [
                'question' => $label,
                'answer' => $connaissances->$field ? 'Oui' : 'Non',
            ];
        }

        return $responses;
    }

    private function formatQuizResponses($quiz): array
    {
        if (!$quiz) {
            return [];
        }

        $questions = [
            'volatilite_risque_gain' => 'La volatilité mesure le niveau de risque et de gain potentiel d’un placement',
            'instruments_tous_cotes' => 'Tous les instruments financiers sont cotés en bourse',
            'risque_liquidite_signification' => 'Le risque de liquidité signifie qu’on pourrait ne pas pouvoir revendre un placement rapidement',
            'livret_a_rendement_negatif' => 'Le livret A peut avoir un rendement réel négatif (après inflation)',
            'assurance_vie_valeur_rachats_uc' => "En assurance vie, la valeur de rachat des UC est toujours garantie",
            'assurance_vie_fiscalite_deces' => 'L’assurance vie bénéficie d’une fiscalité avantageuse en cas de décès',
            'per_non_rachatable' => 'Le PER est en principe non rachetable avant la retraite (sauf exceptions)',
            'per_objectif_revenus_retraite' => 'Le PER a pour objectif de générer des revenus complémentaires à la retraite',
            'compte_titres_ordres_directs' => 'Un compte-titres permet de passer des ordres en direct sur les marchés',
            'pea_actions_europeennes' => 'Le PEA permet d’investir uniquement en actions européennes',
            'opc_pas_de_risque' => 'Les OPC ne présentent aucun risque',
            'opc_definition_fonds_investissement' => 'Un OPC est un fonds d’investissement qui mutualise l’épargne de plusieurs investisseurs',
            'opcvm_actions_plus_risquees' => 'Les OPCVM investis en actions sont plus risqués que ceux investis en obligations',
            'scpi_revenus_garantis' => 'Les SCPI garantissent des revenus locatifs constants',
            'opci_scpi_capital_non_garanti' => 'En OPCI ou SCPI, le capital investi n’est pas garanti',
            'scpi_liquides' => 'Les SCPI sont des placements très liquides',
            'obligations_risque_emetteur' => 'Les obligations comportent un risque lié à la solvabilité de l’émetteur',
            'obligations_cotees_liquidite' => 'Les obligations cotées présentent une liquidité variable selon les titres',
            'obligation_risque_defaut' => 'Une obligation peut faire défaut si l’émetteur ne rembourse pas',
            'parts_sociales_cotees' => 'Les parts sociales sont cotées en bourse',
            'parts_sociales_dividendes_voix' => 'Les parts sociales donnent droit à des dividendes et un droit de vote',
            'fonds_capital_investissement_non_cotes' => 'Les fonds de capital-investissement investissent dans des entreprises non cotées',
            'fcp_rachetable_apres_dissolution' => 'Un FCP n’est rachetable qu’après dissolution',
            'fip_fcpi_reduction_impot' => 'Les FIP et FCPI donnent droit à une réduction d’impôt',
            'actions_non_cotees_risque_perte' => 'Les actions non cotées comportent un risque de perte en capital',
            'actions_cotees_rendement_duree' => 'Les actions cotées sont plus performantes sur longue durée que sur courte durée',
            'produits_structures_complexes' => 'Les produits structurés sont des instruments financiers complexes',
            'produits_structures_risque_defaut_banque' => 'Les produits structurés comportent un risque de défaut de la banque émettrice',
            'etf_fonds_indiciels' => 'Les ETF sont des fonds indiciels cotés',
            'etf_cotes_en_continu' => 'Les ETF sont cotés en continu pendant les heures de bourse',
            'girardin_fonds_perdus' => 'Le Girardin industriel est un investissement à fonds perdus',
            'girardin_non_residents' => 'Le Girardin n’est accessible qu’aux non-résidents fiscaux français',
        ];

        $responses = [];

        foreach ($questions as $field => $label) {
            if (!isset($quiz->$field) || $quiz->$field === null || $quiz->$field === '') {
                continue;
            }

            $responses[] = [
                'question' => $label,
                'answer' => $quiz->$field,
            ];
        }

        return $responses;
    }
}
