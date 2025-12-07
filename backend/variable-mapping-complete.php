<?php

/**
 * Mapping COMPLET pour TOUS les templates (7 templates)
 * Inclut: 5 RC + Recueil Global + Template Mandat
 */

return [
    // ========================================================================
    // === INFORMATIONS CLIENT (table: clients) ===
    // ========================================================================
    'etatcivile' => ['table' => 'clients', 'column' => 'civilite'],
    'Nom' => ['table' => 'clients', 'column' => 'nom'],
    'nom' => ['table' => 'clients', 'column' => 'nom'],
    'Prénom' => ['table' => 'clients', 'column' => 'prenom'],
    'prénom' => ['table' => 'clients', 'column' => 'prenom'],
    'prenom' => ['table' => 'clients', 'column' => 'prenom'],

    // Dates naissance
    'Datedenaissance' => ['table' => 'clients', 'column' => 'date_naissance', 'format' => 'date'],
    'datenaissance' => ['table' => 'clients', 'column' => 'date_naissance', 'format' => 'date'],

    // Lieu naissance
    'Lieudenaissance' => ['table' => 'clients', 'column' => 'lieu_naissance'],
    'lieunaissance' => ['table' => 'clients', 'column' => 'lieu_naissance'],

    // Nationalité
    'nationalite' => ['table' => 'clients', 'column' => 'nationalite'],

    // Situation matrimoniale
    'Situationmatrimoniale' => ['table' => 'clients', 'column' => 'situation_matrimoniale'],
    'situationmatrimoniale' => ['table' => 'clients', 'column' => 'situation_matrimoniale'],
    'situationmatri' => ['table' => 'clients', 'column' => 'situation_matrimoniale'],
    'Datesituationmatri' => ['table' => 'clients', 'column' => 'date_situation_matrimoniale', 'format' => 'date'],

    // Profession
    'Profession' => ['table' => 'clients', 'column' => 'profession'],
    'profession' => ['table' => 'clients', 'column' => 'profession'],
    'professionn' => ['table' => 'clients', 'column' => 'profession'],

    // Situation actuelle
    'Situationactuelle' => ['table' => 'clients', 'column' => 'situation_actuelle'],
    'situationactuelle' => ['table' => 'clients', 'column' => 'situation_actuelle'],
    'situationpro' => ['table' => 'clients', 'column' => 'situation_actuelle'],

    // Statut
    'Statut' => ['table' => 'clients', 'column' => 'statut'],

    // Chef entreprise / Mandataire
    'Mandatairesocial' => ['table' => 'clients', 'column' => 'mandataire_social', 'format' => 'boolean'],
    'chefentreprisee' => ['table' => 'clients', 'column' => 'chef_entreprise', 'format' => 'boolean'],

    // ========================================================================
    // === COORDONNÉES CLIENT ===
    // ========================================================================
    'Adresse' => ['table' => 'clients', 'column' => 'adresse'],
    'adresse' => ['table' => 'clients', 'column' => 'adresse'],
    'CodePostal' => ['table' => 'clients', 'column' => 'code_postal'],
    'codepostal' => ['table' => 'clients', 'column' => 'code_postal'],
    'Ville' => ['table' => 'clients', 'column' => 'ville'],
    'ville' => ['table' => 'clients', 'column' => 'ville'],
    'Mail' => ['table' => 'clients', 'column' => 'email'],
    'mail' => ['table' => 'clients', 'column' => 'email'],
    'email' => ['table' => 'clients', 'column' => 'email'],
    'numerotel' => ['table' => 'clients', 'column' => 'telephone'],
    'tel' => ['table' => 'clients', 'column' => 'telephone'],
    'residencefiscale' => ['table' => 'clients', 'column' => 'residence_fiscale'],

    // ========================================================================
    // === ACTIVITÉS CLIENT ===
    // ========================================================================
    'fumeur' => ['table' => 'clients', 'column' => 'fumeur', 'format' => 'boolean'],
    'activitéssportives' => ['table' => 'clients', 'column' => 'activites_sportives', 'format' => 'boolean'],
    'risquesparticuliers' => ['table' => 'clients', 'column' => 'risques_professionnels', 'format' => 'boolean'],

    // ========================================================================
    // === CONJOINT (table: conjoints) ===
    // ========================================================================
    'Nomconjoint' => ['table' => 'conjoints', 'column' => 'nom'],
    'nomconjoint' => ['table' => 'conjoints', 'column' => 'nom'],
    'Prénomconjoint' => ['table' => 'conjoints', 'column' => 'prenom'],
    'prenomconjoint' => ['table' => 'conjoints', 'column' => 'prenom'],
    'datenaissanceconjoint' => ['table' => 'conjoints', 'column' => 'date_naissance', 'format' => 'date'],
    'lieunaissanceconjoint' => ['table' => 'conjoints', 'column' => 'lieu_naissance'],
    'nationaliteconjoint' => ['table' => 'conjoints', 'column' => 'nationalite'],
    'professionconjointnn' => ['table' => 'conjoints', 'column' => 'profession'],
    'chefentrepriseconjoint' => ['table' => 'conjoints', 'column' => 'chef_entreprise', 'format' => 'boolean'],
    'adresseconjoint' => ['table' => 'conjoints', 'column' => 'adresse'],
    'codepostalconjoint' => ['table' => 'conjoints', 'column' => 'code_postal'],
    'villeconjoint' => ['table' => 'conjoints', 'column' => 'ville'],
    'actuelleconjointsituation' => ['table' => 'conjoints', 'column' => 'situation_actuelle_statut'],
    'nomjeunefille' => ['table' => 'clients', 'column' => 'nom_jeune_fille'],
    'nomjeunefilleconjoint' => ['table' => 'conjoints', 'column' => 'nom_jeune_fille'],

    // ========================================================================
    // === ENFANTS (table: enfants) ===
    // ========================================================================
    'Nombredenfantàcharge' => ['table' => 'enfants', 'column' => 'count'],
    'enfantacharge' => ['table' => 'enfants', 'column' => 'count'],
    'nomprénom1' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 0],
    'nomprénom2' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 1],
    'nomprénom3' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 2],
    'nomprenomenfant1' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 0],
    'nomprenomenfant2' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 1],
    'nomprenomenfant3' => ['table' => 'enfants', 'column' => 'full_name', 'index' => 2],
    'datenaissance1' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 0, 'format' => 'date'],
    'datenaissance2' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 1, 'format' => 'date'],
    'datenaissance3' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 2, 'format' => 'date'],
    'datenaissanceenfant11' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 0, 'format' => 'date'],
    'datenaissanceenfant2' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 1, 'format' => 'date'],
    'datenaissanceenfant3' => ['table' => 'enfants', 'column' => 'date_naissance', 'index' => 2, 'format' => 'date'],
    'parents' => ['type' => 'computed', 'value' => 'parents_names'],
    'gardealternecas' => ['table' => 'enfants', 'column' => 'garde_alternee', 'index' => 0, 'format' => 'boolean'],

    // ========================================================================
    // === REVENUS ET FISCALITÉ (table: bae_retraite) ===
    // ========================================================================
    'Revenusannuel' => ['table' => 'bae_retraite', 'column' => 'revenus_annuels', 'format' => 'currency'],
    'revenuannuelfiscal' => ['table' => 'bae_retraite', 'column' => 'revenus_annuels', 'format' => 'currency'],
    'Impôtsurlerevenupayéenn' => ['table' => 'bae_retraite', 'column' => 'impot_paye_n_1', 'format' => 'currency'],
    'impotrevenunmoins1' => ['table' => 'bae_retraite', 'column' => 'impot_paye_n_1', 'format' => 'currency'],
    'impotrevenunbpart' => ['table' => 'bae_retraite', 'column' => 'nombre_parts_fiscales'],
    'impotrevenutmi' => ['table' => 'bae_retraite', 'column' => 'tmi'],

    // Retraite
    'Agedudépartàlaretraite' => ['table' => 'bae_retraite', 'column' => 'age_depart_retraite'],
    'ageretraitedepart' => ['table' => 'bae_retraite', 'column' => 'age_depart_retraite'],
    'ageretraitedepartconjoint' => ['table' => 'bae_retraite', 'column' => 'age_depart_retraite_conjoint'],
    'siretraiteconjoint' => ['table' => 'bae_retraite', 'column' => 'age_depart_retraite_conjoint', 'format' => 'exists'],
    'siretraitedateeven' => ['table' => 'bae_retraite', 'column' => 'date_evenement_retraite', 'format' => 'date'],
    'bilanretraitee' => ['table' => 'bae_retraite', 'column' => 'bilan_retraite_disponible', 'format' => 'boolean'],
    'complementaireretrairte' => ['table' => 'bae_retraite', 'column' => 'complementaire_retraite_mise_en_place', 'format' => 'boolean'],
    'contratenplacereraite' => ['table' => 'bae_retraite', 'column' => 'contrat_en_place'],
    'contrattitulaireenplace' => ['table' => 'bae_retraite', 'column' => 'titulaire'],
    'cotisationannuelle' => ['table' => 'bae_retraite', 'column' => 'cotisations_annuelles', 'format' => 'currency'],

    // ========================================================================
    // === ÉPARGNE (table: bae_epargne) ===
    // ========================================================================
    'Leclientdispose-t-ilduneépargnedisponible(liquide)' => ['table' => 'bae_epargne', 'column' => 'epargne_disponible', 'format' => 'boolean'],
    'Montantépargnedisponible' => ['table' => 'bae_epargne', 'column' => 'montant_epargne_disponible', 'format' => 'currency'],
    'Capacitédépargneestimée' => ['table' => 'bae_epargne', 'column' => 'capacite_epargne_estimee', 'format' => 'currency'],
    'capaciteepargeestimeee' => ['table' => 'bae_epargne', 'column' => 'capacite_epargne_estimee', 'format' => 'currency'],

    // Patrimoine
    'TotalpatrimoineFinancier' => ['table' => 'bae_epargne', 'column' => 'actifs_financiers_total', 'format' => 'currency'],
    'totalpatrimoinefinancier' => ['table' => 'bae_epargne', 'column' => 'actifs_financiers_total', 'format' => 'currency'],
    'Totalpatrimoineimmobilier' => ['table' => 'bae_epargne', 'column' => 'actifs_immo_total', 'format' => 'currency'],
    'totalpatrimoineimmo' => ['table' => 'bae_epargne', 'column' => 'actifs_immo_total', 'format' => 'currency'],

    // Passifs et charges
    'Totalemprunts' => ['table' => 'bae_epargne', 'column' => 'passifs_total_emprunts', 'format' => 'currency'],
    'Totaldescharges' => ['table' => 'bae_epargne', 'column' => 'charges_totales', 'format' => 'currency'],
    'totalcharges' => ['table' => 'bae_epargne', 'column' => 'charges_totales', 'format' => 'currency'],

    // Situation financière
    'Situationfinancière' => ['table' => 'bae_epargne', 'column' => 'situation_financiere_revenus_charges'],

    // Donations
    'donationbeneficiaire' => ['table' => 'bae_epargne', 'column' => 'donation_beneficiaires'],
    'donationdate' => ['table' => 'bae_epargne', 'column' => 'donation_date', 'format' => 'date'],
    'donationforme' => ['table' => 'bae_epargne', 'column' => 'donation_forme'],
    'donationmontant' => ['table' => 'bae_epargne', 'column' => 'donation_montant', 'format' => 'currency'],

    // Détails actifs (JSON fields - seront gérés spécialement)
    'valeuractuellefinancier1' => ['table' => 'bae_epargne', 'column' => 'actifs_financiers_details', 'json_index' => 0, 'json_field' => 'valeur'],
    'valeuractuellefinancier2' => ['table' => 'bae_epargne', 'column' => 'actifs_financiers_details', 'json_index' => 1, 'json_field' => 'valeur'],
    'valeuractuellefinancier3' => ['table' => 'bae_epargne', 'column' => 'actifs_financiers_details', 'json_index' => 2, 'json_field' => 'valeur'],

    // ========================================================================
    // === PRÉVOYANCE (table: bae_prevoyance) ===
    // ========================================================================
    'siouiprevoyance' => ['table' => 'bae_prevoyance', 'column' => 'contrat_en_place'],
    'siouiprévoyance' => ['table' => 'bae_prevoyance', 'column' => 'contrat_en_place'],
    'Siouiprévoyance' => ['table' => 'bae_prevoyance', 'column' => 'contrat_en_place'],
    'prévoyanceindividuelle' => ['table' => 'bae_prevoyance', 'column' => 'contrat_en_place'],
    'dateeffetgaranties' => ['table' => 'bae_prevoyance', 'column' => 'date_effet', 'format' => 'date'],
    'dategaranties' => ['table' => 'bae_prevoyance', 'column' => 'date_effet', 'format' => 'date'],
    'invaliditécouvert' => ['table' => 'bae_prevoyance', 'column' => 'souhaite_couverture_invalidite', 'format' => 'boolean'],
    'invaliditecouvert' => ['table' => 'bae_prevoyance', 'column' => 'souhaite_couverture_invalidite', 'format' => 'boolean'],
    'revenuagarantir' => ['table' => 'bae_prevoyance', 'column' => 'revenu_a_garantir', 'format' => 'currency'],
    'couvrirchargespro' => ['table' => 'bae_prevoyance', 'column' => 'souhaite_couvrir_charges_professionnelles', 'format' => 'boolean'],
    'chargespro' => ['table' => 'bae_prevoyance', 'column' => 'montant_annuel_charges_professionnelles', 'format' => 'currency'],
    'chargesprofessionnelles' => ['table' => 'bae_prevoyance', 'column' => 'montant_annuel_charges_professionnelles', 'format' => 'currency'],
    'siouicharges' => ['table' => 'bae_prevoyance', 'column' => 'montant_charges_professionnelles_a_garantir', 'format' => 'currency'],
    'couvertarrettravail' => ['table' => 'bae_prevoyance', 'column' => 'duree_indemnisation_souhaitee'],
    'arrettravail' => ['table' => 'bae_prevoyance', 'column' => 'duree_indemnisation_souhaitee'],
    'procheprotecdeces' => ['table' => 'bae_prevoyance', 'column' => 'capital_deces_souhaite', 'format' => 'currency'],
    'casdecesproche' => ['table' => 'bae_prevoyance', 'column' => 'capital_deces_souhaite', 'format' => 'currency'],
    'payeurcotis' => ['table' => 'bae_prevoyance', 'column' => 'payeur'],
    'Payeur' => ['table' => 'bae_prevoyance', 'column' => 'payeur'],
    'chargescouvertes' => ['table' => 'bae_prevoyance', 'column' => 'deplacements_professionnels'],
    'chargesprocouvert' => ['table' => 'bae_prevoyance', 'column' => 'deplacements_professionnels'],

    // ========================================================================
    // === SANTÉ (table: sante_souhaits) ===
    // ========================================================================
    'budgetmax' => ['table' => 'sante_souhaits', 'column' => 'budget_mensuel_maximum', 'format' => 'currency'],
    'budgetsantemax' => ['table' => 'sante_souhaits', 'column' => 'budget_mensuel_maximum', 'format' => 'currency'],
    'contratsanteindiv' => ['table' => 'sante_souhaits', 'column' => 'contrat_en_place'],

    // ========================================================================
    // === QUESTIONNAIRE RISQUE ===
    // ========================================================================

    // Profil et tolérance (table: questionnaire_risques & questionnaire_risque_financiers)
    'Latoléranceaurisqueduclientest' => ['table' => 'questionnaire_risque_financiers', 'column' => 'tolerance_risque'],
    'Leprofilderisqueduclientest' => ['table' => 'questionnaire_risques', 'column' => 'profil_calcule'],
    'profilrisqueclient' => ['table' => 'questionnaire_risques', 'column' => 'profil_calcule'],
    'Pourcentagemaxperte' => ['table' => 'questionnaire_risque_financiers', 'column' => 'pourcentage_perte_max'],
    'Votrehorizond\'investissement' => ['table' => 'questionnaire_risque_financiers', 'column' => 'horizon_investissement'],
    'horizonibjectif' => ['table' => 'questionnaire_risque_financiers', 'column' => 'horizon_investissement'],
    'horizoninvestobjectiff' => ['table' => 'questionnaire_risque_financiers', 'column' => 'horizon_investissement'],
    'Leprésentrapportrépond' => ['table' => 'questionnaire_risque_financiers', 'column' => 'objectif_global'],
    'presentrapportobjectif' => ['table' => 'questionnaire_risque_financiers', 'column' => 'objectif_global'],
    'objectifrapport' => ['table' => 'questionnaire_risque_financiers', 'column' => 'objectif_global'],
    'objectifinvesthorizon' => ['table' => 'questionnaire_risque_financiers', 'column' => 'objectif_placement'],
    'objectifperrevenu' => ['table' => 'questionnaire_risque_financiers', 'column' => 'objectifs_rapport'],

    // Autres questions financières
    'allocationepargneconvientmieux' => ['table' => 'questionnaire_risque_financiers', 'column' => 'allocation_epargne'],
    'boursedegringoleaction25' => ['table' => 'questionnaire_risque_financiers', 'column' => 'reaction_baisse_25'],
    'baissevaleurplaceincidencetrain' => ['table' => 'questionnaire_risque_financiers', 'column' => 'impact_baisse_train_vie'],
    'constatmoinsvaluereaction' => ['table' => 'questionnaire_risque_financiers', 'column' => 'reaction_moins_value'],
    'constituerepargneprecautioncourt' => ['table' => 'questionnaire_risque_financiers', 'column' => 'epargne_precaution', 'format' => 'boolean'],
    'niveaupertesuvirsupporter' => ['table' => 'questionnaire_risque_financiers', 'column' => 'perte_supportable'],
    'partirpertevraimentinquiet' => ['table' => 'questionnaire_risque_financiers', 'column' => 'niveau_perte_inquietude'],
    'sourceinquietfinancier' => ['table' => 'questionnaire_risque_financiers', 'column' => 'placements_inquietude', 'format' => 'boolean'],
    'valeurinvestresterattendrerester' => ['table' => 'questionnaire_risque_financiers', 'column' => 'temps_attente_recuperation_valeur'],
    'affirmationconvientagissantplacem' => ['table' => 'questionnaire_risque_financiers', 'column' => 'attitude_placements'],
    'afffirmatcoorrsponepargne' => ['type' => 'fixed', 'value' => ''], // Pas mappé

    // Questions connaissances (seront simplifiées pour éviter 100+ variables)
    // On va mapper seulement les principales, le reste sera géré dynamiquement

    // ========================================================================
    // === DATES SPÉCIALES ===
    // ========================================================================
    'Date' => ['type' => 'computed', 'value' => 'current_date'],
    'date' => ['type' => 'computed', 'value' => 'current_date'],
    'datedocument' => ['type' => 'computed', 'value' => 'current_date'],
    'Datedudocumentgénérer' => ['type' => 'computed', 'value' => 'current_date'],
    'Datedudocumentgénéré' => ['type' => 'computed', 'value' => 'current_date'],
    'Datedocgener' => ['type' => 'computed', 'value' => 'current_date'],

    // ========================================================================
    // === TEXTES FIXES / QUESTIONS QUIZ ===
    // ========================================================================
    'SOCOGEAvousindique' => ['type' => 'fixed', 'value' => 'SOCOGEA vous indique'],
    'SOCOGEAvousindiqueque' => ['type' => 'fixed', 'value' => 'SOCOGEA vous indique que'],

    // Questions quiz - Ces variables sont des questions, pas des données client
    // On les laisse vides ou on met un texte par défaut
    'capitalgarantieemetteurcapital' => ['type' => 'fixed', 'value' => ''],
    'comptetitreintermediairebroker' => ['type' => 'fixed', 'value' => ''],
    'contratassurvieunitecompte' => ['type' => 'fixed', 'value' => ''],
    'defiscagirandinopéra' => ['type' => 'fixed', 'value' => ''],
    'defiscagirardinopert' => ['type' => 'fixed', 'value' => ''],
    'etfcotecontinujournee' => ['type' => 'fixed', 'value' => ''],
    'etftrackerbaissehausse' => ['type' => 'fixed', 'value' => ''],
    'fcprfcpifipcotebourse' => ['type' => 'fixed', 'value' => ''],
    'fipfcpifcproperationrealis' => ['type' => 'fixed', 'value' => ''],
    'hypothesetauxlivretA' => ['type' => 'fixed', 'value' => ''],
    'inestopciscpisoumis' => ['type' => 'fixed', 'value' => ''],
    'instrufinancierbourse' => ['type' => 'fixed', 'value' => ''],
    'investiractioncoteegenererisque' => ['type' => 'fixed', 'value' => ''],
    'investiractioncoteerendement' => ['type' => 'fixed', 'value' => ''],
    'investirfipfcpirevenu' => ['type' => 'fixed', 'value' => ''],
    'loigirardinpourquoiparler' => ['type' => 'fixed', 'value' => ''],
    'nonresidentdispogirardinbenef' => ['type' => 'fixed', 'value' => ''],
    'obligcoteeoffrent' => ['type' => 'fixed', 'value' => ''],
    'obligrisqueprincipaldebiteur' => ['type' => 'fixed', 'value' => ''],
    'opcinvestfondchoisissez' => ['type' => 'fixed', 'value' => ''],
    'opcpertecapital' => ['type' => 'fixed', 'value' => ''],
    'opcvmdominanteactionoperation' => ['type' => 'fixed', 'value' => ''],
    'opcvmobligatoninvest' => ['type' => 'fixed', 'value' => ''],
    'operationoligopcvm' => ['type' => 'fixed', 'value' => ''],
    'operproditmonetairfondmonet' => ['type' => 'fixed', 'value' => ''],
    'opertfpsslpreala' => ['type' => 'fixed', 'value' => ''],
    'operttitreparticipareal' => ['type' => 'fixed', 'value' => ''],
    'participerdividencegeneral' => ['type' => 'fixed', 'value' => ''],
    'partsocialcoteenbourse' => ['type' => 'fixed', 'value' => ''],
    'partsocialopertreal' => ['type' => 'fixed', 'value' => ''],
    'peacomptetitreopcvm' => ['type' => 'fixed', 'value' => ''],
    'perrachetableprincipe' => ['type' => 'fixed', 'value' => ''],
    'produitmonétfondmonetopera' => ['type' => 'fixed', 'value' => ''],
    'produitstructureperfo' => ['type' => 'fixed', 'value' => ''],
    'produitstrucutederivreal' => ['type' => 'fixed', 'value' => ''],
    'rachetersespartfcprrr' => ['type' => 'fixed', 'value' => ''],
    'realocpiscpioperatio' => ['type' => 'fixed', 'value' => ''],
    'remunerationobligationimportantre' => ['type' => 'fixed', 'value' => ''],
    'revenusscpigarantie' => ['type' => 'fixed', 'value' => ''],
    'risqueliquiditeimportan' => ['type' => 'fixed', 'value' => ''],
    'scpiplacmeznetliquide' => ['type' => 'fixed', 'value' => ''],
    'volatiampleur' => ['type' => 'fixed', 'value' => ''],

    // Variables de détails financiers/immo qui ne sont pas en DB
    // (Ces détails seraient normalement dans des JSON ou tables séparées)
    'anneeacquisitionimmo4' => ['type' => 'fixed', 'value' => ''],
    'anneeacquisitionimmo5' => ['type' => 'fixed', 'value' => ''],
    'anneeacqusiitionimmo6' => ['type' => 'fixed', 'value' => ''],
    'designation4immo' => ['type' => 'fixed', 'value' => ''],
    'designationimmo5' => ['type' => 'fixed', 'value' => ''],
    'designationimmo6' => ['type' => 'fixed', 'value' => ''],
    'detenteur4immo' => ['type' => 'fixed', 'value' => ''],
    'detenteurautre7' => ['type' => 'fixed', 'value' => ''],
    'detenteurfinancier1' => ['type' => 'fixed', 'value' => ''],
    'detenteurfinancier2' => ['type' => 'fixed', 'value' => ''],
    'detenteurimmo5' => ['type' => 'fixed', 'value' => ''],
    'detenteurimmo6' => ['type' => 'fixed', 'value' => ''],
    'dureeresteacourri3' => ['type' => 'fixed', 'value' => ''],
    'epargneautre7' => ['type' => 'fixed', 'value' => ''],
    'epargneautredetenteur8' => ['type' => 'fixed', 'value' => ''],
    'epargneautres' => ['type' => 'fixed', 'value' => ''],
    'epargnedesignation8' => ['type' => 'fixed', 'value' => ''],
    'etablissementfinancier1' => ['type' => 'fixed', 'value' => ''],
    'etablissementfinancier2' => ['type' => 'fixed', 'value' => ''],
    'etablissementfinancier3' => ['type' => 'fixed', 'value' => ''],
    'fiscalcharge1' => ['type' => 'fixed', 'value' => ''],
    'fiscalcharge2' => ['type' => 'fixed', 'value' => ''],
    'fiscalcharge3' => ['type' => 'fixed', 'value' => ''],
    'fiscaspecasdeces' => ['type' => 'fixed', 'value' => ''],
    'formedeproprioimmo5' => ['type' => 'fixed', 'value' => ''],
    'formeproprioimmo4' => ['type' => 'fixed', 'value' => ''],
    'formeproprioimmo6' => ['type' => 'fixed', 'value' => ''],
    'montaannuopertfpsslpp' => ['type' => 'fixed', 'value' => ''],
    'montannupartsocialopert' => ['type' => 'fixed', 'value' => ''],
    'montantD' => ['type' => 'fixed', 'value' => ''],
    'montantE' => ['type' => 'fixed', 'value' => ''],
    'montantannuelactionopcvm' => ['type' => 'fixed', 'value' => ''],
    'montantannuelfipfcprfcpi' => ['type' => 'fixed', 'value' => ''],
    'montantannuelstrucderivoper' => ['type' => 'fixed', 'value' => ''],
    'montantmoyenoperationopciscpi' => ['type' => 'fixed', 'value' => ''],
    'montantmoyentireparticipopert' => ['type' => 'fixed', 'value' => ''],
    'montantopcvmobligannuel' => ['type' => 'fixed', 'value' => ''],
    'montantremboursement1' => ['type' => 'fixed', 'value' => ''],
    'montantremboursement2' => ['type' => 'fixed', 'value' => ''],
    'montantremboursement3' => ['type' => 'fixed', 'value' => ''],
    'nature1financier' => ['type' => 'fixed', 'value' => ''],
    'natureA' => ['type' => 'fixed', 'value' => ''],
    'natureB' => ['type' => 'fixed', 'value' => ''],
    'natureC' => ['type' => 'fixed', 'value' => ''],
    'natureD' => ['type' => 'fixed', 'value' => ''],
    'natureE' => ['type' => 'fixed', 'value' => ''],
    'naturefinancier2' => ['type' => 'fixed', 'value' => ''],
    'naturefinancier3' => ['type' => 'fixed', 'value' => ''],
    'ouvertsouscrit1financier' => ['type' => 'fixed', 'value' => ''],
    'ouvertsouscritfinancier2' => ['type' => 'fixed', 'value' => ''],
    'ouvertsouscritfinancier3' => ['type' => 'fixed', 'value' => ''],
    'passifdureerestant1' => ['type' => 'fixed', 'value' => ''],
    'periodicite1' => ['type' => 'fixed', 'value' => ''],
    'periodicite2' => ['type' => 'fixed', 'value' => ''],
    'periodicite3' => ['type' => 'fixed', 'value' => ''],
    'periodiciteD' => ['type' => 'fixed', 'value' => ''],
    'periodiciteE' => ['type' => 'fixed', 'value' => ''],
    'preteur1passif' => ['type' => 'fixed', 'value' => ''],
    'preteur2' => ['type' => 'fixed', 'value' => ''],
    'preteur3' => ['type' => 'fixed', 'value' => ''],
    'capitalrestantcourir2' => ['type' => 'fixed', 'value' => ''],
    'capitalrestantdu1' => ['type' => 'fixed', 'value' => ''],
    'capitalrestantdu2' => ['type' => 'fixed', 'value' => ''],
    'capitalrestantdu3' => ['type' => 'fixed', 'value' => ''],
    'valeuracquisitionimmo4' => ['type' => 'fixed', 'value' => ''],
    'valeuracquisitionimmo5' => ['type' => 'fixed', 'value' => ''],
    'valeuracquisitionimmo6' => ['type' => 'fixed', 'value' => ''],
    'valeuractuelleestimee4' => ['type' => 'fixed', 'value' => ''],
    'valeuractuelleestimeeimmo6' => ['type' => 'fixed', 'value' => ''],
    'valeurestimeeimmo5' => ['type' => 'fixed', 'value' => ''],

    // ========================================================================
    // === TEMPLATE RECUEIL ADE (39 variables) ===
    // ========================================================================
    'activitesportive' => ['table' => 'clients', 'column' => 'activites_sportives', 'format' => 'boolean'],
    'activitesportiveconjoint' => ['table' => 'conjoints', 'column' => 'details_activites_sportives'],
    'typeactivitesportive' => ['table' => 'clients', 'column' => 'details_activites_sportives'],
    'typeactivitesportiveconjoint' => ['table' => 'conjoints', 'column' => 'details_activites_sportives'],
    'niveauactivitesportiveconjoint' => ['table' => 'conjoints', 'column' => 'niveau_activite_sportive'],
    'adressepersop' => ['table' => 'clients', 'column' => 'adresse'],
    'adressepersoconjoint' => ['table' => 'conjoints', 'column' => 'adresse'],
    'codepostalconjoint' => ['table' => 'conjoints', 'column' => 'code_postal'],
    'villeconjoint' => ['table' => 'conjoints', 'column' => 'ville'],
    'deplacementpro' => ['table' => 'bae_prevoyance', 'column' => 'deplacements_professionnels'],
    'deplacementproconjoint' => ['table' => 'bae_prevoyance', 'column' => 'deplacements_professionnels_conjoint'],
    'fumeurconjoint' => ['type' => 'fixed', 'value' => ''], // Pas de colonne fumeur pour conjoint dans la DB actuelle
    'nbkmparan' => ['table' => 'clients', 'column' => 'km_parcourus_annuels'],
    'nbkmparanconjoint' => ['type' => 'fixed', 'value' => ''], // Pas de colonne km pour conjoint
    'professionactuelleouancienne' => ['table' => 'clients', 'column' => 'profession'],
    'professionactuelleouancienneconjoint' => ['table' => 'conjoints', 'column' => 'profession'],
    'protectionjuridique' => ['table' => 'sante_souhaits', 'column' => 'souhaite_protection_juridique', 'format' => 'boolean'],
    'protectionjuridiqueconjoint' => ['table' => 'sante_souhaits', 'column' => 'souhaite_protection_juridique_conjoint', 'format' => 'boolean'],
    'risqueprofession' => ['table' => 'clients', 'column' => 'risques_professionnels', 'format' => 'boolean'],
    'risqueprofessionconjoint' => ['table' => 'conjoints', 'column' => 'risques_professionnels', 'format' => 'boolean'],
    'siindependant' => ['table' => 'clients', 'column' => 'travailleur_independant', 'format' => 'boolean'],
    'siindependantconjoint' => ['table' => 'conjoints', 'column' => 'travailleur_independant', 'format' => 'boolean'],
    'situationproconjoint' => ['table' => 'conjoints', 'column' => 'situation_actuelle_statut'],
    'statutsiactivite' => ['table' => 'clients', 'column' => 'statut'],
    'statutsiactiviteconjoint' => ['table' => 'conjoints', 'column' => 'statut'],
];
