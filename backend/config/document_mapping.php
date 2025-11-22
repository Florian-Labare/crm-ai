<?php

/**
 * Mapping des variables du template DOCX vers les champs de la base de données
 *
 * Structure:
 * 'variable_template' => [
 *     'source' => 'client|conjoint|enfant|bae_retraite|bae_epargne|computed',
 *     'field' => 'nom_du_champ',
 *     'format' => 'date|currency|boolean|text', // optionnel
 *     'default' => 'valeur_par_defaut', // optionnel
 *     'computed' => function($client) { ... } // pour les champs calculés
 * ]
 */

return [
    // === INFORMATIONS CLIENT ===
    'nom' => ['source' => 'client', 'field' => 'nom'],
    'prenom' => ['source' => 'client', 'field' => 'prenom'],
    'nomjeunefille' => ['source' => 'client', 'field' => 'nom_jeune_fille'],
    'datenaissance' => ['source' => 'client', 'field' => 'date_naissance', 'format' => 'date'],
    'lieunaissance' => ['source' => 'client', 'field' => 'lieu_naissance'],
    'nationalite' => ['source' => 'client', 'field' => 'nationalite'],
    'situationmatrimoniale' => ['source' => 'client', 'field' => 'situation_matrimoniale'],
    'situationactuelle' => ['source' => 'client', 'field' => 'situation_actuelle'],

    // === COORDONNÉES CLIENT ===
    'adresse' => ['source' => 'client', 'field' => 'adresse'],
    'codepostal' => ['source' => 'client', 'field' => 'code_postal'],
    'ville' => ['source' => 'client', 'field' => 'ville'],
    'numerotel' => ['source' => 'client', 'field' => 'telephone'],
    'email' => ['source' => 'client', 'field' => 'email'],

    // === PROFESSIONNEL CLIENT ===
    'professionn' => ['source' => 'client', 'field' => 'profession'],
    'chefentreprisee' => ['source' => 'client', 'field' => 'chef_entreprise', 'format' => 'boolean'],

    // === INFORMATIONS CONJOINT ===
    'nomconjoint' => ['source' => 'conjoint', 'field' => 'nom'],
    'prenomconjoint' => ['source' => 'conjoint', 'field' => 'prenom'],
    'nomjeunefilleconjoint' => ['source' => 'conjoint', 'field' => 'nom_jeune_fille'],
    'datenaissanceconjoint' => ['source' => 'conjoint', 'field' => 'date_naissance', 'format' => 'date'],
    'lieunaissanceconjoint' => ['source' => 'conjoint', 'field' => 'lieu_naissance'],
    'nationaliteconjoint' => ['source' => 'conjoint', 'field' => 'nationalite'],
    'professionconjointnn' => ['source' => 'conjoint', 'field' => 'profession'],
    'chefentrepriseconjoint' => ['source' => 'conjoint', 'field' => 'chef_entreprise', 'format' => 'boolean'],
    'adresseconjoint' => ['source' => 'conjoint', 'field' => 'adresse'],
    'codepostalconjoint' => ['source' => 'conjoint', 'field' => 'code_postal'],
    'villeconjoint' => ['source' => 'conjoint', 'field' => 'ville'],
    'actuelleconjointsituation' => ['source' => 'conjoint', 'field' => 'situation_actuelle_statut'],

    // === ENFANTS (3 enfants) ===
    'nomprenomenfant1' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->enfants->get(0)
            ? $client->enfants->get(0)->prenom . ' ' . $client->enfants->get(0)->nom
            : ''
    ],
    'nomprenomenfant2' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->enfants->get(1)
            ? $client->enfants->get(1)->prenom . ' ' . $client->enfants->get(1)->nom
            : ''
    ],
    'nomprenomenfant3' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->enfants->get(2)
            ? $client->enfants->get(2)->prenom . ' ' . $client->enfants->get(2)->nom
            : ''
    ],
    'datenaissanceenfant11' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->enfants->get(0) && $client->enfants->get(0)->date_naissance
            ? \Carbon\Carbon::parse($client->enfants->get(0)->date_naissance)->format('d/m/Y')
            : ''
    ],
    'datenaissanceenfant2' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->enfants->get(1) && $client->enfants->get(1)->date_naissance
            ? \Carbon\Carbon::parse($client->enfants->get(1)->date_naissance)->format('d/m/Y')
            : ''
    ],
    'datenaissanceenfant3' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->enfants->get(2) && $client->enfants->get(2)->date_naissance
            ? \Carbon\Carbon::parse($client->enfants->get(2)->date_naissance)->format('d/m/Y')
            : ''
    ],
    'gardealternecas' => [
        'source' => 'computed',
        'computed' => function ($client) {
            if ($client->enfants->count() === 0) {
                return '';
            }

            return $client->enfants->where('garde_alternee', true)->count() > 0 ? 'Oui' : 'Non';
        },
    ],
    'parents' => [
        'source' => 'computed',
        'computed' => function ($client) {
            if ($client->enfants->count() === 0) {
                return '';
            }

            $nomClient = trim(($client->prenom ?? '') . ' ' . ($client->nom ?? ''));
            $nomConjoint = $client->conjoint
                ? ' et ' . trim(($client->conjoint->prenom ?? '') . ' ' . ($client->conjoint->nom ?? ''))
                : '';

            return trim($nomClient . $nomConjoint);
        },
    ],

    // === BAE RETRAITE ===
    'ageretraitedepart' => ['source' => 'bae_retraite', 'field' => 'age_depart_retraite'],
    'ageretraitedepartconjoint' => ['source' => 'bae_retraite', 'field' => 'age_depart_retraite_conjoint'],
    'siretraiteconjoint' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeRetraite && $client->baeRetraite->age_depart_retraite_conjoint ? 'Oui' : 'Non'
    ],
    'bilanretraitee' => [
        'source' => 'bae_retraite',
        'field' => 'bilan_retraite_disponible',
        'format' => 'boolean'
    ],
    'contratenplacereraite' => ['source' => 'bae_retraite', 'field' => 'contrat_en_place'],
    'complementaireretrairte' => [
        'source' => 'bae_retraite',
        'field' => 'complementaire_retraite_mise_en_place',
        'format' => 'boolean'
    ],
    'cotisationannuelle' => ['source' => 'bae_retraite', 'field' => 'cotisations_annuelles', 'format' => 'currency'],
    'contrattitulaireenplace' => ['source' => 'bae_retraite', 'field' => 'titulaire'],

    // === DONNÉES FISCALES (depuis bae_retraite) ===
    'revenuannuelfiscal' => ['source' => 'bae_retraite', 'field' => 'revenus_annuels', 'format' => 'currency'],
    'impotrevenunbpart' => ['source' => 'bae_retraite', 'field' => 'nombre_parts_fiscales'],
    'impotrevenunmoins1' => ['source' => 'bae_retraite', 'field' => 'impot_paye_n_1', 'format' => 'currency'],
    'impotrevenutmi' => ['source' => 'bae_retraite', 'field' => 'tmi'],

    // === BAE ÉPARGNE ===
    'capaciteepargeestimeee' => ['source' => 'bae_epargne', 'field' => 'capacite_epargne_estimee', 'format' => 'currency'],
    'totalpatrimoinefinancier' => ['source' => 'bae_epargne', 'field' => 'actifs_financiers_total', 'format' => 'currency'],
    'totalpatrimoineimmo' => ['source' => 'bae_epargne', 'field' => 'actifs_immo_total', 'format' => 'currency'],
    'totalcharges' => ['source' => 'bae_epargne', 'field' => 'charges_totales', 'format' => 'currency'],
    'donationdate' => ['source' => 'bae_epargne', 'field' => 'donation_date', 'format' => 'date'],
    'donationmontant' => ['source' => 'bae_epargne', 'field' => 'donation_montant', 'format' => 'currency'],
    'donationbeneficiaire' => ['source' => 'bae_epargne', 'field' => 'donation_beneficiaires'],
    'donationforme' => ['source' => 'bae_epargne', 'field' => 'donation_forme'],

    // === ACTIFS FINANCIERS (depuis bae_epargne, extraits du JSON) ===
    'nature1financier' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->actifs_financiers_details)
            ? ($client->baeEpargne->actifs_financiers_details[0] ?? '')
            : ''
    ],
    'naturefinancier2' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->actifs_financiers_details)
            ? ($client->baeEpargne->actifs_financiers_details[1] ?? '')
            : ''
    ],
    'naturefinancier3' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->actifs_financiers_details)
            ? ($client->baeEpargne->actifs_financiers_details[2] ?? '')
            : ''
    ],

    // === ACTIFS IMMOBILIERS (depuis bae_epargne, extraits du JSON) ===
    'designation4immo' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->actifs_immo_details)
            ? ($client->baeEpargne->actifs_immo_details[0] ?? '')
            : ''
    ],
    'designationimmo5' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->actifs_immo_details)
            ? ($client->baeEpargne->actifs_immo_details[1] ?? '')
            : ''
    ],
    'designationimmo6' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->actifs_immo_details)
            ? ($client->baeEpargne->actifs_immo_details[2] ?? '')
            : ''
    ],

    // === PASSIFS (depuis bae_epargne, extraits du JSON) ===
    'preteur1passif' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->passifs_details)
            ? ($client->baeEpargne->passifs_details[0] ?? '')
            : ''
    ],
    'preteur2' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->passifs_details)
            ? ($client->baeEpargne->passifs_details[1] ?? '')
            : ''
    ],
    'preteur3' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->passifs_details)
            ? ($client->baeEpargne->passifs_details[2] ?? '')
            : ''
    ],

    // === CHARGES (depuis bae_epargne, extraits du JSON) ===
    'fiscalcharge1' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->charges_details)
            ? ($client->baeEpargne->charges_details[0] ?? '')
            : ''
    ],
    'fiscalcharge2' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->charges_details)
            ? ($client->baeEpargne->charges_details[1] ?? '')
            : ''
    ],
    'fiscalcharge3' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->baeEpargne && is_array($client->baeEpargne->charges_details)
            ? ($client->baeEpargne->charges_details[2] ?? '')
            : ''
    ],

    // === DATES ET METADATA ===
    'Date' => [
        'source' => 'computed',
        'computed' => fn($client) => now()->format('d/m/Y')
    ],

    // === QUESTIONNAIRE - COMPORTEMENT FINANCIER ===
    'valeurinvestresterattendrerester' => [
        'source' => 'questionnaire_financier',
        'field' => 'temps_attente_recuperation_valeur',
        'format' => 'enum',
        'mapping' => [
            'moins_1_an' => 'Moins d\'1 an',
            '1_3_ans' => 'Entre 1 an et 3 ans',
            'plus_3_ans' => 'Plus de 3 ans',
        ],
    ],
    'partirpertevraimentinquiet' => [
        'source' => 'questionnaire_financier',
        'field' => 'niveau_perte_inquietude',
        'format' => 'enum',
        'mapping' => [
            'perte_5' => '5 %',
            'perte_20' => '20 %',
            'pas_inquietude' => 'Pas d\'inquiétude (je sais que cela peut remonter)',
        ],
    ],
    'boursedegringoleaction25' => [
        'source' => 'questionnaire_financier',
        'field' => 'reaction_baisse_25',
        'format' => 'enum',
        'mapping' => [
            'vendre_partie' => 'J’hésite peut-être à vendre une partie',
            'acheter_plus' => 'J’achète plus de ces actions',
            'vendre_tout' => 'Je vends tout sans attendre',
        ],
    ],
    'affirmationconvientagissantplacem' => [
        'source' => 'questionnaire_financier',
        'field' => 'attitude_placements',
        'format' => 'enum',
        'mapping' => [
            'eviter_pertes' => 'Je redoute avant tout les pertes',
            'recherche_gains' => 'Je m’intéresse surtout aux gains',
            'equilibre_gains' => 'Je m’intéresse aux deux',
        ],
    ],
    'allocationepargneconvientmieux' => [
        'source' => 'questionnaire_financier',
        'field' => 'allocation_epargne',
        'format' => 'enum',
        'mapping' => [
            'allocation_70_30' => '70% croissance / 30% défensifs',
            'allocation_30_70' => '30% croissance / 70% défensifs',
            'allocation_50_50' => '50% croissance / 50% défensifs',
        ],
    ],
    'afffirmatcoorrsponepargne' => [
        'source' => 'questionnaire_financier',
        'field' => 'objectif_placement',
        'format' => 'enum',
        'mapping' => [
            'protection_capital' => 'La protection du capital est ma priorité',
            'risque_modere' => 'Je suis prêt à prendre des risques modérés',
            'risque_important' => 'Je suis prêt à prendre des risques importants',
        ],
    ],
    'sourceinquietfinancier' => [
        'source' => 'questionnaire_financier',
        'field' => 'placements_inquietude',
        'format' => 'boolean',
    ],
    'constituerepargneprecautioncourt' => [
        'source' => 'questionnaire_financier',
        'field' => 'epargne_precaution',
        'format' => 'boolean',
    ],
    'constatmoinsvaluereaction' => [
        'source' => 'questionnaire_financier',
        'field' => 'reaction_moins_value',
        'format' => 'enum',
        'mapping' => [
            'contacter_immediat' => 'Appeler immédiatement le conseiller',
            'voir_plus_tard' => 'Attendre le prochain rendez-vous',
        ],
    ],
    'baissevaleurplaceincidencetrain' => [
        'source' => 'questionnaire_financier',
        'field' => 'impact_baisse_train_vie',
        'format' => 'enum',
        'mapping' => [
            'aucun_impact' => 'Aucun impact sur mon train de vie',
            'ajustements' => 'Nécessite quelques ajustements',
            'fort_impact' => 'Impact important sur mon train de vie',
        ],
    ],
    'niveaupertesuvirsupporter' => [
        'source' => 'questionnaire_financier',
        'field' => 'perte_supportable',
        'format' => 'enum',
        'mapping' => [
            'aucune_perte' => 'Aucune perte',
            'perte_10' => '10% du capital investi',
            'perte_25' => '25% du capital investi',
            'perte_50' => '50% du capital investi',
            'perte_capital' => 'Jusqu’à la totalité du capital',
        ],
    ],
    'presentrapportobjectif' => [
        'source' => 'questionnaire_financier',
        'field' => 'objectifs_rapport',
    ],
    'horizoninvestobjectiff' => [
        'source' => 'questionnaire_financier',
        'field' => 'horizon_investissement',
        'format' => 'enum',
        'mapping' => [
            'court_terme' => 'Court terme (moins de 3 ans)',
            'moyen_terme' => 'Moyen terme (3 à 8 ans)',
            'long_terme' => 'Long terme (plus de 8 ans)',
        ],
    ],
    'objectifinvesthorizon' => [
        'source' => 'questionnaire_financier',
        'field' => 'objectif_global',
        'format' => 'enum',
        'mapping' => [
            'securitaire' => 'Sécuritaire (préservation du capital)',
            'revenus' => 'Revenus (dividendes, etc.)',
            'croissance' => 'Croissance (faire fructifier le capital)',
            'protection' => 'Sécuritaire (préservation du capital)',
            'equilibre' => 'Équilibre',
            'performance' => 'Performance',
        ],
    ],

    // === QUESTIONNAIRE - CONNAISSANCES PRODUITS ===
    'operationoligopcvm' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_obligations',
        'format' => 'boolean',
    ],
    'montantopcvmobligannuel' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_obligations',
        'format' => 'currency',
    ],
    'opcvmdominanteactionoperation' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_actions',
        'format' => 'boolean',
    ],
    'montantannuelactionopcvm' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_actions',
        'format' => 'currency',
    ],
    'fipfcpifcproperationrealis' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_fip_fcpi',
        'format' => 'boolean',
    ],
    'montantannuelfipfcprfcpi' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_fip_fcpi',
        'format' => 'currency',
    ],
    'realocpiscpioperatio' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_opci_scpi',
        'format' => 'boolean',
    ],
    'montantmoyenoperationopciscpi' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_opci_scpi',
        'format' => 'currency',
    ],
    'produitstrucutederivreal' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_produits_structures',
        'format' => 'boolean',
    ],
    'montantannuelstrucderivoper' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_produits_structures',
        'format' => 'currency',
    ],
    'produitmonétfondmonetopera' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_monetaires',
        'format' => 'boolean',
    ],
    'operproditmonetairfondmonet' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_monetaires',
        'format' => 'currency',
    ],
    'partsocialopertreal' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_parts_sociales',
        'format' => 'boolean',
    ],
    'montannupartsocialopert' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_parts_sociales',
        'format' => 'currency',
    ],
    'operttitreparticipareal' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_titres_participatifs',
        'format' => 'boolean',
    ],
    'montantmoyentireparticipopert' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_titres_participatifs',
        'format' => 'currency',
    ],
    'opertfpsslpreala' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_fps_slp',
        'format' => 'boolean',
    ],
    'montaannuopertfpsslpp' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_fps_slp',
        'format' => 'currency',
    ],
    'defiscagirardinopert' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'connaissance_girardin',
        'format' => 'boolean',
    ],
    'defiscagirandinopéra' => [
        'source' => 'questionnaire_connaissances',
        'field' => 'montant_girardin',
        'format' => 'currency',
    ],

    // === QUESTIONNAIRE - QUIZ (VRAI / FAUX) ===
    'volatiampleur' => [
        'source' => 'questionnaire_quiz',
        'field' => 'volatilite_risque_gain',
        'format' => 'quiz',
    ],
    'instrufinancierbourse' => [
        'source' => 'questionnaire_quiz',
        'field' => 'instruments_tous_cotes',
        'format' => 'quiz',
    ],
    'risqueliquiditeimportan' => [
        'source' => 'questionnaire_quiz',
        'field' => 'risque_liquidite_signification',
        'format' => 'quiz',
    ],
    'hypothesetauxlivretA' => [
        'source' => 'questionnaire_quiz',
        'field' => 'livret_a_rendement_negatif',
        'format' => 'quiz',
    ],
    'contratassurvieunitecompte' => [
        'source' => 'questionnaire_quiz',
        'field' => 'assurance_vie_valeur_rachats_uc',
        'format' => 'quiz',
    ],
    'fiscaspecasdeces' => [
        'source' => 'questionnaire_quiz',
        'field' => 'assurance_vie_fiscalite_deces',
        'format' => 'quiz',
    ],
    'perrachetableprincipe' => [
        'source' => 'questionnaire_quiz',
        'field' => 'per_non_rachatable',
        'format' => 'quiz',
    ],
    'objectifperrevenu' => [
        'source' => 'questionnaire_quiz',
        'field' => 'per_objectif_revenus_retraite',
        'format' => 'quiz',
    ],
    'comptetitreintermediairebroker' => [
        'source' => 'questionnaire_quiz',
        'field' => 'compte_titres_ordres_directs',
        'format' => 'quiz',
    ],
    'peacomptetitreopcvm' => [
        'source' => 'questionnaire_quiz',
        'field' => 'pea_actions_europeennes',
        'format' => 'quiz',
    ],
    'opcpertecapital' => [
        'source' => 'questionnaire_quiz',
        'field' => 'opc_pas_de_risque',
        'format' => 'quiz',
    ],
    'opcinvestfondchoisissez' => [
        'source' => 'questionnaire_quiz',
        'field' => 'opc_definition_fonds_investissement',
        'format' => 'quiz',
    ],
    'opcvmobligatoninvest' => [
        'source' => 'questionnaire_quiz',
        'field' => 'opcvm_actions_plus_risquees',
        'format' => 'quiz',
    ],
    'revenusscpigarantie' => [
        'source' => 'questionnaire_quiz',
        'field' => 'scpi_revenus_garantis',
        'format' => 'quiz',
    ],
    'inestopciscpisoumis' => [
        'source' => 'questionnaire_quiz',
        'field' => 'opci_scpi_capital_non_garanti',
        'format' => 'quiz',
    ],
    'scpiplacmeznetliquide' => [
        'source' => 'questionnaire_quiz',
        'field' => 'scpi_liquides',
        'format' => 'quiz',
    ],
    'remunerationobligationimportantre' => [
        'source' => 'questionnaire_quiz',
        'field' => 'obligations_risque_emetteur',
        'format' => 'quiz',
    ],
    'obligcoteeoffrent' => [
        'source' => 'questionnaire_quiz',
        'field' => 'obligations_cotees_liquidite',
        'format' => 'quiz',
    ],
    'obligrisqueprincipaldebiteur' => [
        'source' => 'questionnaire_quiz',
        'field' => 'obligation_risque_defaut',
        'format' => 'quiz',
    ],
    'partsocialcoteenbourse' => [
        'source' => 'questionnaire_quiz',
        'field' => 'parts_sociales_cotees',
        'format' => 'quiz',
    ],
    'participerdividencegeneral' => [
        'source' => 'questionnaire_quiz',
        'field' => 'parts_sociales_dividendes_voix',
        'format' => 'quiz',
    ],
    'fcprfcpifipcotebourse' => [
        'source' => 'questionnaire_quiz',
        'field' => 'fonds_capital_investissement_non_cotes',
        'format' => 'quiz',
    ],
    'rachetersespartfcprrr' => [
        'source' => 'questionnaire_quiz',
        'field' => 'fcp_rachetable_apres_dissolution',
        'format' => 'quiz',
    ],
    'investirfipfcpirevenu' => [
        'source' => 'questionnaire_quiz',
        'field' => 'fip_fcpi_reduction_impot',
        'format' => 'quiz',
    ],
    'investiractioncoteegenererisque' => [
        'source' => 'questionnaire_quiz',
        'field' => 'actions_non_cotees_risque_perte',
        'format' => 'quiz',
    ],
    'investiractioncoteerendement' => [
        'source' => 'questionnaire_quiz',
        'field' => 'actions_cotees_rendement_duree',
        'format' => 'quiz',
    ],
    'produitstructureperfo' => [
        'source' => 'questionnaire_quiz',
        'field' => 'produits_structures_complexes',
        'format' => 'quiz',
    ],
    'capitalgarantieemetteurcapital' => [
        'source' => 'questionnaire_quiz',
        'field' => 'produits_structures_risque_defaut_banque',
        'format' => 'quiz',
    ],
    'etftrackerbaissehausse' => [
        'source' => 'questionnaire_quiz',
        'field' => 'etf_fonds_indiciels',
        'format' => 'quiz',
    ],
    'etfcotecontinujournee' => [
        'source' => 'questionnaire_quiz',
        'field' => 'etf_cotes_en_continu',
        'format' => 'quiz',
    ],
    'loigirardinpourquoiparler' => [
        'source' => 'questionnaire_quiz',
        'field' => 'girardin_fonds_perdus',
        'format' => 'quiz',
    ],
    'nonresidentdispogirardinbenef' => [
        'source' => 'questionnaire_quiz',
        'field' => 'girardin_non_residents',
        'format' => 'quiz',
    ],

    // Variables non identifiées ou spécifiques à remplir plus tard
    'siretraitedateeven' => ['default' => ''],
    'anneeacquisitionimmo4' => ['default' => ''],
    'anneeacquisitionimmo5' => ['default' => ''],
    'anneeacqusiitionimmo6' => ['default' => ''],
    'capitalrestantcourir2' => ['default' => ''],
    'capitalrestantdu1' => ['default' => ''],
    'capitalrestantdu2' => ['default' => ''],
    'capitalrestantdu3' => ['default' => ''],
    'detenteur4immo' => ['default' => ''],
    'detenteurautre7' => ['default' => ''],
    'detenteurfinancier1' => ['default' => ''],
    'detenteurfinancier2' => ['default' => ''],
    'detenteurimmo5' => ['default' => ''],
    'detenteurimmo6' => ['default' => ''],
    'dureeresteacourri3' => ['default' => ''],
    'epargneautre7' => ['default' => ''],
    'epargneautredetenteur8' => ['default' => ''],
    'epargneautres' => ['default' => ''],
    'epargnedesignation8' => ['default' => ''],
    'etablissementfinancier1' => ['default' => ''],
    'etablissementfinancier2' => ['default' => ''],
    'etablissementfinancier3' => ['default' => ''],
    'fiscaspecasdeces' => ['default' => ''],
    'formedeproprioimmo5' => ['default' => ''],
    'formeproprioimmo4' => ['default' => ''],
    'formeproprioimmo6' => ['default' => ''],
    'montantD' => ['default' => ''],
    'montantE' => ['default' => ''],
    'montantremboursement1' => ['default' => ''],
    'montantremboursement2' => ['default' => ''],
    'montantremboursement3' => ['default' => ''],
    'natureA' => ['default' => ''],
    'natureB' => ['default' => ''],
    'natureC' => ['default' => ''],
    'natureD' => ['default' => ''],
    'natureE' => ['default' => ''],
    'ouvertsouscrit1financier' => ['default' => ''],
    'ouvertsouscritfinancier2' => ['default' => ''],
    'ouvertsouscritfinancier3' => ['default' => ''],
    'passifdureerestant1' => ['default' => ''],
    'periodicite1' => ['default' => ''],
    'periodicite2' => ['default' => ''],
    'periodicite3' => ['default' => ''],
    'periodiciteD' => ['default' => ''],
    'periodiciteE' => ['default' => ''],
    'valeuracquisitionimmo4' => ['default' => ''],
    'valeuracquisitionimmo5' => ['default' => ''],
    'valeuracquisitionimmo6' => ['default' => ''],
    'valeuractuelleestimee4' => ['default' => ''],
    'valeuractuelleestimeeimmo6' => ['default' => ''],
    'valeuractuellefinancier1' => ['default' => ''],
    'valeuractuellefinancier2' => ['default' => ''],
    'valeuractuellefinancier3' => ['default' => ''],
    'valeurestimeeimmo5' => ['default' => ''],
];
