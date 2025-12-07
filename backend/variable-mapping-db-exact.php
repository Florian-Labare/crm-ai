<?php

/**
 * Mapping EXACT entre les variables actuelles des templates et les colonnes de la base de données
 *
 * Format: 'variable_actuelle' => [
 *     'table' => 'nom_table',
 *     'column' => 'nom_colonne',
 *     'format' => 'type_formatage' (optionnel)
 * ]
 */

return [
    // === INFORMATIONS CLIENT (table: clients) ===
    'etatcivile' => ['table' => 'clients', 'column' => 'civilite'],
    'Nom' => ['table' => 'clients', 'column' => 'nom'],
    'nom' => ['table' => 'clients', 'column' => 'nom'],
    'Prénom' => ['table' => 'clients', 'column' => 'prenom'],
    'prénom' => ['table' => 'clients', 'column' => 'prenom'],
    'Datedenaissance' => ['table' => 'clients', 'column' => 'date_naissance', 'format' => 'date'],
    'datenaissance' => ['table' => 'clients', 'column' => 'date_naissance', 'format' => 'date'],
    'datenaissance1' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 0, 'format' => 'date'],
    'datenaissance2' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 1, 'format' => 'date'],
    'datenaissance3' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 2, 'format' => 'date'],
    'Lieudenaissance' => ['table' => 'clients', 'column' => 'lieu_naissance'],
    'lieunaissance' => ['table' => 'clients', 'column' => 'lieu_naissance'],
    'Situationmatrimoniale' => ['table' => 'clients', 'column' => 'situation_matrimoniale'],
    'situationmatri' => ['table' => 'clients', 'column' => 'situation_matrimoniale'],
    'Profession' => ['table' => 'clients', 'column' => 'profession'],
    'profession' => ['table' => 'clients', 'column' => 'profession'],
    'Situationactuelle' => ['table' => 'clients', 'column' => 'situation_actuelle'],
    'situationpro' => ['table' => 'clients', 'column' => 'situation_actuelle'],
    'Statut' => ['table' => 'clients', 'column' => 'statut'],
    'Mandatairesocial' => ['table' => 'clients', 'column' => 'mandataire_social', 'format' => 'boolean'],

    // === COORDONNÉES CLIENT ===
    'Adresse' => ['table' => 'clients', 'column' => 'adresse'],
    'adresse' => ['table' => 'clients', 'column' => 'adresse'],
    'CodePostal' => ['table' => 'clients', 'column' => 'code_postal'],
    'Ville' => ['table' => 'clients', 'column' => 'ville'],
    'Mail' => ['table' => 'clients', 'column' => 'email'],
    'mail' => ['table' => 'clients', 'column' => 'email'],
    'residencefiscale' => ['table' => 'clients', 'column' => 'residence_fiscale'],

    // === ACTIVITÉS CLIENT ===
    'fumeur' => ['table' => 'clients', 'column' => 'fumeur', 'format' => 'boolean'],
    'activitéssportives' => ['table' => 'clients', 'column' => 'activites_sportives', 'format' => 'boolean'],
    'risquesparticuliers' => ['table' => 'clients', 'column' => 'risques_professionnels', 'format' => 'boolean'],

    // === REVENUS ET FISCALITÉ (table: bae_retraite) ===
    'Revenusannuel' => ['table' => 'bae_retraite', 'column' => 'revenus_annuels', 'format' => 'currency'],
    'Impôtsurlerevenupayéenn' => ['table' => 'bae_retraite', 'column' => 'impot_paye_n_1', 'format' => 'currency'],
    'Agedudépartàlaretraite' => ['table' => 'bae_retraite', 'column' => 'age_depart_retraite'],

    // === ÉPARGNE (table: bae_epargne) ===
    'Leclientdispose-t-ilduneépargnedisponible(liquide)' => ['table' => 'bae_epargne', 'column' => 'epargne_disponible', 'format' => 'boolean'],
    'Montantépargnedisponible' => ['table' => 'bae_epargne', 'column' => 'montant_epargne_disponible', 'format' => 'currency'],
    'Capacitédépargneestimée' => ['table' => 'bae_epargne', 'column' => 'capacite_epargne_estimee', 'format' => 'currency'],
    'TotalpatrimoineFinancier' => ['table' => 'bae_epargne', 'column' => 'actifs_financiers_total', 'format' => 'currency'],
    'Totalpatrimoineimmobilier' => ['table' => 'bae_epargne', 'column' => 'actifs_immo_total', 'format' => 'currency'],
    'Totalemprunts' => ['table' => 'bae_epargne', 'column' => 'passifs_total_emprunts', 'format' => 'currency'],
    'Totaldescharges' => ['table' => 'bae_epargne', 'column' => 'charges_totales', 'format' => 'currency'],
    'Situationfinancière' => ['table' => 'bae_epargne', 'column' => 'situation_financiere_revenus_charges'],

    // === PRÉVOYANCE (table: bae_prevoyance) ===
    'siouiprevoyance' => ['table' => 'bae_prevoyance', 'column' => 'contrat_en_place'],
    'siouiprévoyance' => ['table' => 'bae_prevoyance', 'column' => 'contrat_en_place'],
    'dateeffetgaranties' => ['table' => 'bae_prevoyance', 'column' => 'date_effet', 'format' => 'date'],
    'invaliditécouvert' => ['table' => 'bae_prevoyance', 'column' => 'souhaite_couverture_invalidite', 'format' => 'boolean'],
    'revenuagarantir' => ['table' => 'bae_prevoyance', 'column' => 'revenu_a_garantir', 'format' => 'currency'],
    'couvrirchargespro' => ['table' => 'bae_prevoyance', 'column' => 'souhaite_couvrir_charges_professionnelles', 'format' => 'boolean'],
    'chargespro' => ['table' => 'bae_prevoyance', 'column' => 'montant_annuel_charges_professionnelles', 'format' => 'currency'],
    'siouicharges' => ['table' => 'bae_prevoyance', 'column' => 'montant_charges_professionnelles_a_garantir', 'format' => 'currency'],
    'couvertarrettravail' => ['table' => 'bae_prevoyance', 'column' => 'duree_indemnisation_souhaitee'],
    'procheprotecdeces' => ['table' => 'bae_prevoyance', 'column' => 'capital_deces_souhaite', 'format' => 'currency'],
    'payeurcotis' => ['table' => 'bae_prevoyance', 'column' => 'payeur'],
    'chargescouvertes' => ['table' => 'bae_prevoyance', 'column' => 'deplacements_professionnels'],

    // === SANTÉ (table: sante_souhaits) ===
    'budgetmax' => ['table' => 'sante_souhaits', 'column' => 'budget_mensuel_maximum', 'format' => 'currency'],

    // === CONJOINT (table: conjoints) ===
    'Nomconjoint' => ['table' => 'conjoints', 'column' => 'nom'],
    'Prénomconjoint' => ['table' => 'conjoints', 'column' => 'prenom'],

    // === ENFANTS (table: enfants) ===
    'Nombredenfantàcharge' => ['table' => 'enfants', 'column' => 'count'],
    'nomprénom1' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 0],
    'nomprénom2' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 1],
    'nomprénom3' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 2],

    // === QUESTIONNAIRE RISQUE (table: questionnaire_risque_financiers) ===
    'Latoléranceaurisqueduclientest' => ['table' => 'questionnaire_risque_financiers', 'column' => 'tolerance_risque'],
    'Leprofilderisqueduclientest' => ['table' => 'questionnaire_risques', 'column' => 'profil_calcule'],
    'Pourcentagemaxperte' => ['table' => 'questionnaire_risque_financiers', 'column' => 'pourcentage_perte_max'],
    'Votrehorizond\'investissement' => ['table' => 'questionnaire_risque_financiers', 'column' => 'horizon_investissement'],
    'Leprésentrapportrépond' => ['table' => 'questionnaire_risque_financiers', 'column' => 'objectif_global'],

    // === DATES SPÉCIALES ===
    'Date' => ['type' => 'computed', 'value' => 'current_date'],
    'date' => ['type' => 'computed', 'value' => 'current_date'],
    'datedocument' => ['type' => 'computed', 'value' => 'current_date'],
    'Datedudocumentgénérer' => ['type' => 'computed', 'value' => 'current_date'],
    'Datedudocumentgénéré' => ['type' => 'computed', 'value' => 'current_date'],

    // === TEXTES FIXES (à remplacer par des valeurs configurables) ===
    'SOCOGEAvousindique' => ['type' => 'fixed', 'value' => 'SOCOGEA vous indique'],
    'SOCOGEAvousindiqueque' => ['type' => 'fixed', 'value' => 'SOCOGEA vous indique que'],
];
