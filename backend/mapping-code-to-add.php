<?php

/**
 * Code à ajouter dans config/document_mapping.php
 */

return [

    // === VARIABLES AJOUTÉES AUTOMATIQUEMENT - MIGRATION COMPLÈTE ===

    // === SANTÉ - SOUHAITS CLIENT ===
    'AnalyseImagerie' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_imagerie',
        'format' => 'boolean',
    ], // Imagerie médicale (IRM, scanner, radio)
    'AuxiliairesMédicaux' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_auxiliaires_medicaux',
        'format' => 'boolean',
    ], // Infirmiers, kinés, orthophonistes
    'Dentaire' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_dentaire',
        'format' => 'boolean',
    ], // Soins dentaires
    'Hospitalisation' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_hospitalisation',
        'format' => 'boolean',
    ], // Couverture hospitalisation
    'MédecinGénéralisteetspécialiste' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_medecins',
        'format' => 'boolean',
    ], // Consultations médecins
    'autresprotheses' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_autres_protheses',
        'format' => 'boolean',
    ], // Prothèses diverses (hors auditives)
    'curesthermales' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_cures_thermales',
        'format' => 'boolean',
    ], // Cures thermales
    'medecinedouce' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_medecine_douce',
        'format' => 'boolean',
    ], // Ostéopathie, acupuncture, etc.
    'optiquelentilles' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_optique',
        'format' => 'boolean',
    ], // Lunettes et lentilles
    'protheseauditive' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_prothese_auditive',
        'format' => 'boolean',
    ], // Appareils auditifs
    'protectionjuridique' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_protection_juridique',
        'format' => 'boolean',
    ], // Protection juridique
    'protectionjuridiqueconjoint' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_protection_juridique_conjoint',
        'format' => 'boolean',
    ], // Protection juridique conjoint

    // === BAE RETRAITE - COMPLÉMENTS ===
    'Impôtsurlerevenupayéenn' => [
        'source' => 'bae_retraite',
        'field' => 'impot_paye_n_1',
        'format' => 'currency',
    ], // Alias de impotrevenunmoins1 (existe déjà)
    'Agedudépartàlaretraite' => [
        'source' => 'bae_retraite',
        'field' => 'age_depart_retraite',
        'format' => 'number',
    ], // Alias de ageretraitedepart (existe)
    'dateretraiteevenement' => [
        'source' => 'bae_retraite',
        'field' => 'date_evenement_retraite',
        'format' => 'date',
    ], // Date prévue départ à la retraite

    // === BAE ÉPARGNE - COMPLÉMENTS ===
    'Montantépargnedisponible' => [
        'source' => 'bae_epargne',
        'field' => 'montant_epargne_disponible',
        'format' => 'currency',
    ], // Épargne liquide disponible
    'Totalemprunts' => [
        'source' => 'bae_epargne',
        'field' => 'total_emprunts',
        'format' => 'currency',
    ], // Total des emprunts en cours

    // === CHAMPS CALCULÉS ===
    'Leclientdispose-t-ilduneépargnedisponible(liquide)' => [
        'source' => 'computed',
        'computed' => function ($client) {
            if (! $client->baeEpargne || ! $client->baeEpargne->montant_epargne_disponible) {
                return 'Non';
            }

            return $client->baeEpargne->montant_epargne_disponible > 0 ? 'Oui' : 'Non';
        },
    ], // Calculé: montant_epargne_disponible > 0
    'SOCOGEAvousindique' => [
        'source' => 'computed',
        'computed' => fn ($client) => 'SOCOGEA vous indique',
    ], // Texte statique commercial
    'SOCOGEAvousindiqueque' => [
        'source' => 'computed',
        'computed' => fn ($client) => 'SOCOGEA vous indique que',
    ], // Texte statique commercial
    'Leprésentrapportrépond' => [
        'source' => 'computed',
        'computed' => fn ($client) => 'Le présent rapport répond',
    ], // Texte statique rapport

    // === QUESTIONNAIRE RISQUE ===
    'Latoléranceaurisqueduclientest' => [
        'source' => 'questionnaire_risque',
        'field' => 'tolerance_risque',
    ], // Description tolérance au risque
    'Pourcentagemaxperte' => [
        'source' => 'questionnaire_risque',
        'field' => 'pourcentage_max_perte',
        'format' => 'number',
    ], // Perte maximale acceptable (%)

    // === QUESTIONNAIRE FINANCIER - COMPLÉMENTS ===
    "Votrehorizond'investissement" => [
        'source' => 'questionnaire_financier',
        'field' => 'horizon_investissement',
        'format' => 'enum',
    ], // Existe déjà dans horizoninvestobjectiff

    // === CLIENT - COMPLÉMENTS ===
    'Travailleurindépendant' => [
        'source' => 'client',
        'field' => 'travailleur_independant',
        'format' => 'boolean',
    ], // Statut indépendant
    'siindependant' => [
        'source' => 'client',
        'field' => 'travailleur_independant',
        'format' => 'boolean',
    ], // Alias de Travailleurindépendant
    'professionactuelleouancienne' => [
        'source' => 'client',
        'field' => 'profession',
    ], // Alias de profession (existe)
    'situationpro' => [
        'source' => 'client',
        'field' => 'situation_professionnelle',
    ], // Situation professionnelle détaillée
    'statutsiactivite' => [
        'source' => 'client',
        'field' => 'statut',
    ], // Alias de Statut (existe)
    'siouimandataire' => [
        'source' => 'client',
        'field' => 'mandataire_social',
        'format' => 'boolean',
    ], // Alias de Mandatairesocial (existe)
    'niveauactivite' => [
        'source' => 'client',
        'field' => 'niveau_activite_sportive',
    ], // Occasionnel/Régulier/Intensif
    'nbkmparan' => [
        'source' => 'client',
        'field' => 'km_parcourus_annuels',
        'format' => 'number',
    ], // Kilomètres parcourus par an (véhicule)
    'Résidencefiscale' => [
        'source' => 'client',
        'field' => 'pays_residence_fiscale',
    ], // Pays de résidence fiscale
    'residencefiscale' => [
        'source' => 'client',
        'field' => 'pays_residence_fiscale',
    ], // Alias de Résidencefiscale
    'Téléphone' => [
        'source' => 'client',
        'field' => 'telephone',
    ], // Alias de numerotel (existe)
    'adressepersop' => [
        'source' => 'client',
        'field' => 'adresse',
    ], // Alias de adresse (existe)
    'etatcivile' => [
        'source' => 'client',
        'field' => 'situation_matrimoniale',
    ], // Alias de situationmatrimoniale (existe)
    'genre' => [
        'source' => 'client',
        'field' => 'genre',
        'format' => 'enum',
    ], // Sexe: M/F

    // === CONJOINT - COMPLÉMENTS ===
    'siindependantconjoint' => [
        'source' => 'conjoint',
        'field' => 'travailleur_independant',
        'format' => 'boolean',
    ], // Statut indépendant conjoint
    'professionactuelleouancienneconjoint' => [
        'source' => 'conjoint',
        'field' => 'profession',
    ], // Alias de professionconjointnn (existe)
    'situationproconjoint' => [
        'source' => 'conjoint',
        'field' => 'situation_professionnelle',
    ], // Situation pro conjoint
    'statutsiactiviteconjoint' => [
        'source' => 'conjoint',
        'field' => 'statut',
    ], // Statut professionnel conjoint
    'niveauactivitesportiveconjoint' => [
        'source' => 'conjoint',
        'field' => 'niveau_activite_sportive',
    ], // Niveau activité sportive conjoint
    'typeactivitesportiveconjoint' => [
        'source' => 'conjoint',
        'field' => 'details_activites_sportives',
    ], // Type d'activité sportive conjoint
    'situationconjointchomage' => [
        'source' => 'conjoint',
        'field' => 'situation_chomage',
        'format' => 'boolean',
    ], // Conjoint au chômage

    // === BAE PRÉVOYANCE - COMPLÉMENTS ===
    'deplacementpro' => [
        'source' => 'bae_prevoyance',
        'field' => 'deplacements_professionnels',
    ], // Nature des déplacements pro
    'deplacementproconjoint' => [
        'source' => 'bae_prevoyance',
        'field' => 'deplacements_professionnels_conjoint',
    ], // Déplacements pro conjoint
    'dureeindemnisationfraispro' => [
        'source' => 'bae_prevoyance',
        'field' => 'duree_indemnisation_frais_pro',
    ], // Durée souhaitée indemnisation
    'montantannuelprocouvert' => [
        'source' => 'bae_prevoyance',
        'field' => 'montant_annuel_frais_pro',
        'format' => 'currency',
    ], // Montant annuel frais pro à garantir
    'couvertinvalidite' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_couverture_invalidite',
        'format' => 'boolean',
    ], // Alias de invaliditecouvert (existe)
    'couvrirchargespro' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_couvrir_charges_professionnelles',
        'format' => 'boolean',
    ], // Alias de chargesprocouvert (existe)
    'dénominationcontratprev' => [
        'source' => 'bae_prevoyance',
        'field' => 'denomination_contrat',
    ], // Nom du contrat prévoyance actuel
    'montantprevgarantie' => [
        'source' => 'bae_prevoyance',
        'field' => 'montant_garanti',
        'format' => 'currency',
    ], // Montant de garantie souhaité
    'procheprotecdeces' => [
        'source' => 'bae_prevoyance',
        'field' => 'capital_deces_souhaite',
        'format' => 'currency',
    ], // Alias de casdecesproche (existe)
    'siouicharges' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_couvrir_charges_professionnelles',
        'format' => 'boolean',
    ], // Duplicate de couvrirchargespro
    'siouioutillage' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_garantie_outillage',
        'format' => 'boolean',
    ], // Garantie outillage professionnel
    'montantchargecouverte' => [
        'source' => 'bae_prevoyance',
        'field' => 'montant_charges_professionnelles_a_garantir',
        'format' => 'currency',
    ], // Alias de chargesprofessionnelles (existe)

];
