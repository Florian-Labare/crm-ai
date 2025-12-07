<?php

/**
 * Code à ajouter dans config/document_mapping.php
 */

return [

    // === VARIABLES AJOUTÉES AUTOMATIQUEMENT - MIGRATION COMPLÈTE ===

    // === SANTÉ - SOUHAITS CLIENT ===
    'AnalyseImagerie' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_imagerie',
  'format' => 'boolean',
), // Imagerie médicale (IRM, scanner, radio)
    'AuxiliairesMédicaux' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_auxiliaires_medicaux',
  'format' => 'boolean',
), // Infirmiers, kinés, orthophonistes
    'Dentaire' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_dentaire',
  'format' => 'boolean',
), // Soins dentaires
    'Hospitalisation' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_hospitalisation',
  'format' => 'boolean',
), // Couverture hospitalisation
    'MédecinGénéralisteetspécialiste' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_medecins',
  'format' => 'boolean',
), // Consultations médecins
    'autresprotheses' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_autres_protheses',
  'format' => 'boolean',
), // Prothèses diverses (hors auditives)
    'curesthermales' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_cures_thermales',
  'format' => 'boolean',
), // Cures thermales
    'medecinedouce' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_medecine_douce',
  'format' => 'boolean',
), // Ostéopathie, acupuncture, etc.
    'optiquelentilles' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_optique',
  'format' => 'boolean',
), // Lunettes et lentilles
    'protheseauditive' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_prothese_auditive',
  'format' => 'boolean',
), // Appareils auditifs
    'protectionjuridique' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_protection_juridique',
  'format' => 'boolean',
), // Protection juridique
    'protectionjuridiqueconjoint' => array (
  'source' => 'sante_souhait',
  'field' => 'souhaite_protection_juridique_conjoint',
  'format' => 'boolean',
), // Protection juridique conjoint

    // === BAE RETRAITE - COMPLÉMENTS ===
    'Impôtsurlerevenupayéenn' => array (
  'source' => 'bae_retraite',
  'field' => 'impot_paye_n_1',
  'format' => 'currency',
), // Alias de impotrevenunmoins1 (existe déjà)
    'Agedudépartàlaretraite' => array (
  'source' => 'bae_retraite',
  'field' => 'age_depart_retraite',
  'format' => 'number',
), // Alias de ageretraitedepart (existe)
    'dateretraiteevenement' => array (
  'source' => 'bae_retraite',
  'field' => 'date_evenement_retraite',
  'format' => 'date',
), // Date prévue départ à la retraite

    // === BAE ÉPARGNE - COMPLÉMENTS ===
    'Montantépargnedisponible' => array (
  'source' => 'bae_epargne',
  'field' => 'montant_epargne_disponible',
  'format' => 'currency',
), // Épargne liquide disponible
    'Totalemprunts' => array (
  'source' => 'bae_epargne',
  'field' => 'total_emprunts',
  'format' => 'currency',
), // Total des emprunts en cours

    // === CHAMPS CALCULÉS ===
    'Leclientdispose-t-ilduneépargnedisponible(liquide)' => [
        'source' => 'computed',
        'computed' => function ($client) {
            if (!$client->baeEpargne || !$client->baeEpargne->montant_epargne_disponible) {
                return 'Non';
            }
            return $client->baeEpargne->montant_epargne_disponible > 0 ? 'Oui' : 'Non';
        },
    ], // Calculé: montant_epargne_disponible > 0
    'SOCOGEAvousindique' => [
        'source' => 'computed',
        'computed' => fn($client) => 'SOCOGEA vous indique',
    ], // Texte statique commercial
    'SOCOGEAvousindiqueque' => [
        'source' => 'computed',
        'computed' => fn($client) => 'SOCOGEA vous indique que',
    ], // Texte statique commercial
    'Leprésentrapportrépond' => [
        'source' => 'computed',
        'computed' => fn($client) => 'Le présent rapport répond',
    ], // Texte statique rapport

    // === QUESTIONNAIRE RISQUE ===
    'Latoléranceaurisqueduclientest' => array (
  'source' => 'questionnaire_risque',
  'field' => 'tolerance_risque',
), // Description tolérance au risque
    'Pourcentagemaxperte' => array (
  'source' => 'questionnaire_risque',
  'field' => 'pourcentage_max_perte',
  'format' => 'number',
), // Perte maximale acceptable (%)

    // === QUESTIONNAIRE FINANCIER - COMPLÉMENTS ===
    'Votrehorizond'investissement' => array (
  'source' => 'questionnaire_financier',
  'field' => 'horizon_investissement',
  'format' => 'enum',
), // Existe déjà dans horizoninvestobjectiff

    // === CLIENT - COMPLÉMENTS ===
    'Travailleurindépendant' => array (
  'source' => 'client',
  'field' => 'travailleur_independant',
  'format' => 'boolean',
), // Statut indépendant
    'siindependant' => array (
  'source' => 'client',
  'field' => 'travailleur_independant',
  'format' => 'boolean',
), // Alias de Travailleurindépendant
    'professionactuelleouancienne' => array (
  'source' => 'client',
  'field' => 'profession',
), // Alias de profession (existe)
    'situationpro' => array (
  'source' => 'client',
  'field' => 'situation_professionnelle',
), // Situation professionnelle détaillée
    'statutsiactivite' => array (
  'source' => 'client',
  'field' => 'statut',
), // Alias de Statut (existe)
    'siouimandataire' => array (
  'source' => 'client',
  'field' => 'mandataire_social',
  'format' => 'boolean',
), // Alias de Mandatairesocial (existe)
    'niveauactivite' => array (
  'source' => 'client',
  'field' => 'niveau_activite_sportive',
), // Occasionnel/Régulier/Intensif
    'nbkmparan' => array (
  'source' => 'client',
  'field' => 'km_parcourus_annuels',
  'format' => 'number',
), // Kilomètres parcourus par an (véhicule)
    'Résidencefiscale' => array (
  'source' => 'client',
  'field' => 'pays_residence_fiscale',
), // Pays de résidence fiscale
    'residencefiscale' => array (
  'source' => 'client',
  'field' => 'pays_residence_fiscale',
), // Alias de Résidencefiscale
    'Téléphone' => array (
  'source' => 'client',
  'field' => 'telephone',
), // Alias de numerotel (existe)
    'adressepersop' => array (
  'source' => 'client',
  'field' => 'adresse',
), // Alias de adresse (existe)
    'etatcivile' => array (
  'source' => 'client',
  'field' => 'situation_matrimoniale',
), // Alias de situationmatrimoniale (existe)
    'genre' => array (
  'source' => 'client',
  'field' => 'genre',
  'format' => 'enum',
), // Sexe: M/F

    // === CONJOINT - COMPLÉMENTS ===
    'siindependantconjoint' => array (
  'source' => 'conjoint',
  'field' => 'travailleur_independant',
  'format' => 'boolean',
), // Statut indépendant conjoint
    'professionactuelleouancienneconjoint' => array (
  'source' => 'conjoint',
  'field' => 'profession',
), // Alias de professionconjointnn (existe)
    'situationproconjoint' => array (
  'source' => 'conjoint',
  'field' => 'situation_professionnelle',
), // Situation pro conjoint
    'statutsiactiviteconjoint' => array (
  'source' => 'conjoint',
  'field' => 'statut',
), // Statut professionnel conjoint
    'niveauactivitesportiveconjoint' => array (
  'source' => 'conjoint',
  'field' => 'niveau_activite_sportive',
), // Niveau activité sportive conjoint
    'typeactivitesportiveconjoint' => array (
  'source' => 'conjoint',
  'field' => 'details_activites_sportives',
), // Type d'activité sportive conjoint
    'situationconjointchomage' => array (
  'source' => 'conjoint',
  'field' => 'situation_chomage',
  'format' => 'boolean',
), // Conjoint au chômage

    // === BAE PRÉVOYANCE - COMPLÉMENTS ===
    'deplacementpro' => array (
  'source' => 'bae_prevoyance',
  'field' => 'deplacements_professionnels',
), // Nature des déplacements pro
    'deplacementproconjoint' => array (
  'source' => 'bae_prevoyance',
  'field' => 'deplacements_professionnels_conjoint',
), // Déplacements pro conjoint
    'dureeindemnisationfraispro' => array (
  'source' => 'bae_prevoyance',
  'field' => 'duree_indemnisation_frais_pro',
), // Durée souhaitée indemnisation
    'montantannuelprocouvert' => array (
  'source' => 'bae_prevoyance',
  'field' => 'montant_annuel_frais_pro',
  'format' => 'currency',
), // Montant annuel frais pro à garantir
    'couvertinvalidite' => array (
  'source' => 'bae_prevoyance',
  'field' => 'souhaite_couverture_invalidite',
  'format' => 'boolean',
), // Alias de invaliditecouvert (existe)
    'couvrirchargespro' => array (
  'source' => 'bae_prevoyance',
  'field' => 'souhaite_couvrir_charges_professionnelles',
  'format' => 'boolean',
), // Alias de chargesprocouvert (existe)
    'dénominationcontratprev' => array (
  'source' => 'bae_prevoyance',
  'field' => 'denomination_contrat',
), // Nom du contrat prévoyance actuel
    'montantprevgarantie' => array (
  'source' => 'bae_prevoyance',
  'field' => 'montant_garanti',
  'format' => 'currency',
), // Montant de garantie souhaité
    'procheprotecdeces' => array (
  'source' => 'bae_prevoyance',
  'field' => 'capital_deces_souhaite',
  'format' => 'currency',
), // Alias de casdecesproche (existe)
    'siouicharges' => array (
  'source' => 'bae_prevoyance',
  'field' => 'souhaite_couvrir_charges_professionnelles',
  'format' => 'boolean',
), // Duplicate de couvrirchargespro
    'siouioutillage' => array (
  'source' => 'bae_prevoyance',
  'field' => 'souhaite_garantie_outillage',
  'format' => 'boolean',
), // Garantie outillage professionnel
    '​​montantchargecouverte' => array (
  'source' => 'bae_prevoyance',
  'field' => 'montant_charges_professionnelles_a_garantir',
  'format' => 'currency',
), // Alias de chargesprofessionnelles (existe)

];
