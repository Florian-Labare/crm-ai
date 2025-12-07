<?php

/**
 * Code à ajouter dans config/document_mapping.php
 * MAPPING CORRIGÉ basé sur les champs réellement existants en base
 */

return [
    // === SANTÉ - Mapping vers champs existants niveau_* ===
    // On mappe vers les champs niveau_* existants au lieu de créer des souhaite_*
    'AnalyseImagerie' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_analyses_imagerie ? 'Oui' : 'Non',
    ],
    'AuxiliairesMédicaux' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_auxiliaires_medicaux ? 'Oui' : 'Non',
    ],
    'Dentaire' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_dentaire ? 'Oui' : 'Non',
    ],
    'Hospitalisation' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_hospitalisation ? 'Oui' : 'Non',
    ],
    'MédecinGénéralisteetspécialiste' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_medecin_generaliste ? 'Oui' : 'Non',
    ],
    'autresprotheses' => ['source' => 'sante_souhait', 'field' => 'souhaite_autres_protheses', 'format' => 'boolean'],
    'curesthermales' => ['source' => 'sante_souhait', 'field' => 'souhaite_cures_thermales', 'format' => 'boolean'],
    'medecinedouce' => ['source' => 'sante_souhait', 'field' => 'souhaite_medecine_douce', 'format' => 'boolean'],
    'optiquelentilles' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_optique ? 'Oui' : 'Non',
    ],
    'protheseauditive' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->santeSouhait && $client->santeSouhait->niveau_protheses_auditives ? 'Oui' : 'Non',
    ],
    'protectionjuridique' => ['source' => 'sante_souhait', 'field' => 'souhaite_protection_juridique', 'format' => 'boolean'],
    'protectionjuridiqueconjoint' => ['source' => 'sante_souhait', 'field' => 'souhaite_protection_juridique_conjoint', 'format' => 'boolean'],

    // === FINANCIERS ===
    'Impôtsurlerevenupayéenn' => ['source' => 'bae_retraite', 'field' => 'impot_paye_n_1', 'format' => 'currency'],
    'Montantépargnedisponible' => ['source' => 'bae_epargne', 'field' => 'montant_epargne_disponible', 'format' => 'currency'],
    'Totalemprunts' => ['source' => 'bae_epargne', 'field' => 'passifs_total_emprunts', 'format' => 'currency'],
    'Leclientdispose-t-ilduneépargnedisponible(liquide)' => [
        'source' => 'computed',
        'computed' => function ($client) {
            if (!$client->baeEpargne || !$client->baeEpargne->montant_epargne_disponible) {
                return 'Non';
            }
            return $client->baeEpargne->montant_epargne_disponible > 0 ? 'Oui' : 'Non';
        },
    ],

    // === PROFIL DE RISQUE - Mapping vers questionnaire_risque_financiers ===
    'Latoléranceaurisqueduclientest' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->questionnaireRisque?->questionnaireFinancier?->tolerance_risque ?? 'Non défini',
    ],
    'Pourcentagemaxperte' => [
        'source' => 'computed',
        'computed' => fn($client) => $client->questionnaireRisque?->questionnaireFinancier?->pourcentage_perte_max ?? '',
    ],
    'Votrehorizond\'investissement' => [
        'source' => 'questionnaire_financier',
        'field' => 'horizon_investissement',
        'format' => 'enum',
        'mapping' => [
            'court_terme' => 'Court terme (moins de 3 ans)',
            'moyen_terme' => 'Moyen terme (3 à 8 ans)',
            'long_terme' => 'Long terme (plus de 8 ans)',
        ],
    ],

    // === PROFESSIONNELS ===
    'Travailleurindépendant' => ['source' => 'client', 'field' => 'travailleur_independant', 'format' => 'boolean'],
    'siindependant' => ['source' => 'client', 'field' => 'travailleur_independant', 'format' => 'boolean'],
    'siindependantconjoint' => ['source' => 'conjoint', 'field' => 'travailleur_independant', 'format' => 'boolean'],
    'deplacementpro' => ['source' => 'bae_prevoyance', 'field' => 'deplacements_professionnels'],
    'deplacementproconjoint' => ['source' => 'bae_prevoyance', 'field' => 'deplacements_professionnels_conjoint'],
    'dureeindemnisationfraispro' => ['source' => 'bae_prevoyance', 'field' => 'duree_indemnisation_frais_pro'],
    'montantannuelprocouvert' => ['source' => 'bae_prevoyance', 'field' => 'montant_annuel_charges_professionnelles', 'format' => 'currency'],
    'professionactuelleouancienne' => ['source' => 'client', 'field' => 'profession'],
    'professionactuelleouancienneconjoint' => ['source' => 'conjoint', 'field' => 'profession'],
    'situationpro' => ['source' => 'client', 'field' => 'situation_professionnelle'],
    'situationproconjoint' => ['source' => 'conjoint', 'field' => 'situation_professionnelle'],
    'statutsiactivite' => ['source' => 'client', 'field' => 'statut'],
    'statutsiactiviteconjoint' => ['source' => 'conjoint', 'field' => 'statut'],

    // === PRÉVOYANCE ===
    'couvertinvalidite' => ['source' => 'bae_prevoyance', 'field' => 'souhaite_couverture_invalidite', 'format' => 'boolean'],
    'couvrirchargespro' => ['source' => 'bae_prevoyance', 'field' => 'souhaite_couvrir_charges_professionnelles', 'format' => 'boolean'],
    'dénominationcontratprev' => ['source' => 'bae_prevoyance', 'field' => 'denomination_contrat'],
    'montantprevgarantie' => ['source' => 'bae_prevoyance', 'field' => 'montant_garanti', 'format' => 'currency'],
    'procheprotecdeces' => ['source' => 'bae_prevoyance', 'field' => 'capital_deces_souhaite', 'format' => 'currency'],
    'siouicharges' => ['source' => 'bae_prevoyance', 'field' => 'souhaite_couvrir_charges_professionnelles', 'format' => 'boolean'],
    'siouimandataire' => ['source' => 'client', 'field' => 'mandataire_social', 'format' => 'boolean'],
    'siouioutillage' => ['source' => 'bae_prevoyance', 'field' => 'souhaite_garantie_outillage', 'format' => 'boolean'],
    '​​montantchargecouverte' => ['source' => 'bae_prevoyance', 'field' => 'montant_charges_professionnelles_a_garantir', 'format' => 'currency'],

    // === ACTIVITÉ SPORTIVE ===
    'niveauactivite' => ['source' => 'client', 'field' => 'niveau_activites_sportives'],
    'niveauactivitesportiveconjoint' => ['source' => 'conjoint', 'field' => 'niveau_activite_sportive'],
    'typeactivitesportiveconjoint' => ['source' => 'conjoint', 'field' => 'details_activites_sportives'],
    'nbkmparan' => ['source' => 'client', 'field' => 'km_parcourus_annuels', 'format' => 'number'],

    // === RETRAITE ===
    'Agedudépartàlaretraite' => ['source' => 'bae_retraite', 'field' => 'age_depart_retraite', 'format' => 'number'],
    'dateretraiteevenement' => ['source' => 'bae_retraite', 'field' => 'date_evenement_retraite', 'format' => 'date'],

    // === GÉNÉRAUX ===
    'Résidencefiscale' => ['source' => 'client', 'field' => 'residence_fiscale'],
    'residencefiscale' => ['source' => 'client', 'field' => 'residence_fiscale'],
    'Téléphone' => ['source' => 'client', 'field' => 'telephone'],
    'adressepersop' => ['source' => 'client', 'field' => 'adresse'],
    'etatcivile' => ['source' => 'client', 'field' => 'situation_matrimoniale'],
    'genre' => ['source' => 'client', 'field' => 'genre', 'format' => 'enum'],
    'SOCOGEAvousindique' => [
        'source' => 'computed',
        'computed' => fn($client) => 'SOCOGEA vous indique',
    ],
    'SOCOGEAvousindiqueque' => [
        'source' => 'computed',
        'computed' => fn($client) => 'SOCOGEA vous indique que',
    ],
    'Leprésentrapportrépond' => [
        'source' => 'computed',
        'computed' => fn($client) => 'Le présent rapport répond',
    ],

    // === CONJOINT ===
    'situationconjointchomage' => ['source' => 'conjoint', 'field' => 'situation_chomage', 'format' => 'boolean'],
];
