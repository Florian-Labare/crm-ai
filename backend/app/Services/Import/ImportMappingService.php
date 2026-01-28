<?php

namespace App\Services\Import;

use App\Models\ImportMapping;
use Illuminate\Support\Str;

class ImportMappingService
{
    /**
     * Complete database schema definition covering ALL client-related tables
     */
    private const DATABASE_SCHEMA = [
        // ==================== CLIENT (main table) ====================
        'client' => [
            'civilite' => ['type' => 'enum', 'values' => ['Monsieur', 'Madame']],
            'nom' => ['type' => 'string', 'required' => true],
            'nom_jeune_fille' => ['type' => 'string'],
            'prenom' => ['type' => 'string', 'required' => true],
            'date_naissance' => ['type' => 'date'],
            'lieu_naissance' => ['type' => 'string'],
            'nationalite' => ['type' => 'string'],
            'situation_matrimoniale' => ['type' => 'string'],
            'date_situation_matrimoniale' => ['type' => 'date'],
            'situation_actuelle' => ['type' => 'string'],
            'date_evenement_professionnel' => ['type' => 'date'],
            'profession' => ['type' => 'string'],
            'statut' => ['type' => 'string'],
            'chef_entreprise' => ['type' => 'boolean'],
            'travailleur_independant' => ['type' => 'boolean'],
            'mandataire_social' => ['type' => 'boolean'],
            'risques_professionnels' => ['type' => 'boolean'],
            'details_risques_professionnels' => ['type' => 'text'],
            'revenus_annuels' => ['type' => 'decimal'],
            'adresse' => ['type' => 'string'],
            'code_postal' => ['type' => 'string'],
            'ville' => ['type' => 'string'],
            'residence_fiscale' => ['type' => 'string'],
            'telephone' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'fumeur' => ['type' => 'boolean'],
            'activites_sportives' => ['type' => 'boolean'],
            'details_activites_sportives' => ['type' => 'text'],
            'niveau_activites_sportives' => ['type' => 'string'],
            'km_parcourus_annuels' => ['type' => 'integer'],
        ],

        // ==================== CONJOINT ====================
        'conjoint' => [
            // Identité
            'conjoint_nom' => ['type' => 'string', 'db_field' => 'nom'],
            'conjoint_nom_jeune_fille' => ['type' => 'string', 'db_field' => 'nom_jeune_fille'],
            'conjoint_prenom' => ['type' => 'string', 'db_field' => 'prenom'],
            'conjoint_date_naissance' => ['type' => 'date', 'db_field' => 'datedenaissance'],
            'conjoint_lieu_naissance' => ['type' => 'string', 'db_field' => 'lieudenaissance'],
            'conjoint_nationalite' => ['type' => 'string', 'db_field' => 'nationalite'],
            // Coordonnées
            'conjoint_telephone' => ['type' => 'string', 'db_field' => 'telephone'],
            'conjoint_email' => ['type' => 'string', 'db_field' => 'email'],
            'conjoint_adresse' => ['type' => 'string', 'db_field' => 'adresse'],
            'conjoint_code_postal' => ['type' => 'string', 'db_field' => 'code_postal'],
            'conjoint_ville' => ['type' => 'string', 'db_field' => 'ville'],
            // Professionnel
            'conjoint_profession' => ['type' => 'string', 'db_field' => 'profession'],
            'conjoint_situation_professionnelle' => ['type' => 'string', 'db_field' => 'situation_professionnelle'],
            'conjoint_situation_chomage' => ['type' => 'string', 'db_field' => 'situation_chomage'],
            'conjoint_statut' => ['type' => 'string', 'db_field' => 'statut'],
            'conjoint_chef_entreprise' => ['type' => 'boolean', 'db_field' => 'chef_entreprise'],
            'conjoint_travailleur_independant' => ['type' => 'boolean', 'db_field' => 'travailleur_independant'],
            'conjoint_mandataire_social' => ['type' => 'boolean', 'db_field' => 'mandataire_social'],
            'conjoint_situation_actuelle' => ['type' => 'string', 'db_field' => 'situation_actuelle_statut'],
            'conjoint_date_evenement_professionnel' => ['type' => 'date', 'db_field' => 'date_evenement_professionnel'],
            'conjoint_risques_professionnels' => ['type' => 'boolean', 'db_field' => 'risques_professionnels'],
            'conjoint_details_risques_professionnels' => ['type' => 'text', 'db_field' => 'details_risques_professionnels'],
            'conjoint_revenus_annuels' => ['type' => 'decimal', 'db_field' => 'revenus_annuels'],
            // Mode de vie
            'conjoint_fumeur' => ['type' => 'boolean', 'db_field' => 'fumeur'],
            'conjoint_activites_sportives' => ['type' => 'boolean', 'db_field' => 'activites_sportives'],
            'conjoint_niveau_activite_sportive' => ['type' => 'string', 'db_field' => 'niveau_activite_sportive'],
            'conjoint_details_activites_sportives' => ['type' => 'text', 'db_field' => 'details_activites_sportives'],
            'conjoint_km_parcourus_annuels' => ['type' => 'integer', 'db_field' => 'km_parcourus_annuels'],
        ],

        // ==================== ENFANTS (indexed) ====================
        'enfant' => [
            'enfant1_nom' => ['type' => 'string', 'db_field' => 'nom', 'index' => 1],
            'enfant1_prenom' => ['type' => 'string', 'db_field' => 'prenom', 'index' => 1],
            'enfant1_nom_prenom' => ['type' => 'string', 'db_field' => 'nom_prenom', 'index' => 1, 'composite' => true],
            'enfant1_date_naissance' => ['type' => 'date', 'db_field' => 'datedenaissance', 'index' => 1],
            'enfant1_fiscalement_a_charge' => ['type' => 'boolean', 'db_field' => 'fiscalement_a_charge', 'index' => 1],
            'enfant1_garde_alternee' => ['type' => 'boolean', 'db_field' => 'garde_alternee', 'index' => 1],
            'enfant2_nom' => ['type' => 'string', 'db_field' => 'nom', 'index' => 2],
            'enfant2_prenom' => ['type' => 'string', 'db_field' => 'prenom', 'index' => 2],
            'enfant2_nom_prenom' => ['type' => 'string', 'db_field' => 'nom_prenom', 'index' => 2, 'composite' => true],
            'enfant2_date_naissance' => ['type' => 'date', 'db_field' => 'datedenaissance', 'index' => 2],
            'enfant2_fiscalement_a_charge' => ['type' => 'boolean', 'db_field' => 'fiscalement_a_charge', 'index' => 2],
            'enfant2_garde_alternee' => ['type' => 'boolean', 'db_field' => 'garde_alternee', 'index' => 2],
            'enfant3_nom' => ['type' => 'string', 'db_field' => 'nom', 'index' => 3],
            'enfant3_prenom' => ['type' => 'string', 'db_field' => 'prenom', 'index' => 3],
            'enfant3_nom_prenom' => ['type' => 'string', 'db_field' => 'nom_prenom', 'index' => 3, 'composite' => true],
            'enfant3_date_naissance' => ['type' => 'date', 'db_field' => 'datedenaissance', 'index' => 3],
            'enfant3_fiscalement_a_charge' => ['type' => 'boolean', 'db_field' => 'fiscalement_a_charge', 'index' => 3],
            'enfant3_garde_alternee' => ['type' => 'boolean', 'db_field' => 'garde_alternee', 'index' => 3],
            'enfant4_nom' => ['type' => 'string', 'db_field' => 'nom', 'index' => 4],
            'enfant4_prenom' => ['type' => 'string', 'db_field' => 'prenom', 'index' => 4],
            'enfant4_nom_prenom' => ['type' => 'string', 'db_field' => 'nom_prenom', 'index' => 4, 'composite' => true],
            'enfant4_date_naissance' => ['type' => 'date', 'db_field' => 'datedenaissance', 'index' => 4],
            'enfant4_fiscalement_a_charge' => ['type' => 'boolean', 'db_field' => 'fiscalement_a_charge', 'index' => 4],
            'enfant4_garde_alternee' => ['type' => 'boolean', 'db_field' => 'garde_alternee', 'index' => 4],
            'nombre_enfants' => ['type' => 'integer', 'db_field' => '_meta'],
        ],

        // ==================== SANTE_SOUHAITS (Health/Mutuelle) ====================
        'sante_souhaits' => [
            'sante_contrat_en_place' => ['type' => 'string', 'db_field' => 'contrat_en_place'],
            'sante_budget_mensuel_maximum' => ['type' => 'decimal', 'db_field' => 'budget_mensuel_maximum'],
            'sante_niveau_hospitalisation' => ['type' => 'integer', 'db_field' => 'niveau_hospitalisation'],
            'sante_niveau_chambre_particuliere' => ['type' => 'integer', 'db_field' => 'niveau_chambre_particuliere'],
            'sante_niveau_medecin_generaliste' => ['type' => 'integer', 'db_field' => 'niveau_medecin_generaliste'],
            'sante_niveau_analyses_imagerie' => ['type' => 'integer', 'db_field' => 'niveau_analyses_imagerie'],
            'sante_niveau_auxiliaires_medicaux' => ['type' => 'integer', 'db_field' => 'niveau_auxiliaires_medicaux'],
            'sante_niveau_pharmacie' => ['type' => 'integer', 'db_field' => 'niveau_pharmacie'],
            'sante_niveau_dentaire' => ['type' => 'integer', 'db_field' => 'niveau_dentaire'],
            'sante_niveau_optique' => ['type' => 'integer', 'db_field' => 'niveau_optique'],
            'sante_niveau_protheses_auditives' => ['type' => 'integer', 'db_field' => 'niveau_protheses_auditives'],
            'sante_souhaite_medecine_douce' => ['type' => 'boolean', 'db_field' => 'souhaite_medecine_douce'],
            'sante_souhaite_cures_thermales' => ['type' => 'boolean', 'db_field' => 'souhaite_cures_thermales'],
            'sante_souhaite_autres_protheses' => ['type' => 'boolean', 'db_field' => 'souhaite_autres_protheses'],
            'sante_souhaite_protection_juridique' => ['type' => 'boolean', 'db_field' => 'souhaite_protection_juridique'],
            'sante_souhaite_protection_juridique_conjoint' => ['type' => 'boolean', 'db_field' => 'souhaite_protection_juridique_conjoint'],
        ],

        // ==================== BAE_PREVOYANCE (Protection/Insurance) ====================
        'bae_prevoyance' => [
            'prevoyance_contrat_en_place' => ['type' => 'string', 'db_field' => 'contrat_en_place'],
            'prevoyance_date_effet' => ['type' => 'date', 'db_field' => 'date_effet'],
            'prevoyance_cotisations' => ['type' => 'decimal', 'db_field' => 'cotisations'],
            'prevoyance_souhaite_couverture_invalidite' => ['type' => 'boolean', 'db_field' => 'souhaite_couverture_invalidite'],
            'prevoyance_revenu_a_garantir' => ['type' => 'decimal', 'db_field' => 'revenu_a_garantir'],
            'prevoyance_souhaite_couvrir_charges_professionnelles' => ['type' => 'boolean', 'db_field' => 'souhaite_couvrir_charges_professionnelles'],
            'prevoyance_montant_annuel_charges_professionnelles' => ['type' => 'decimal', 'db_field' => 'montant_annuel_charges_professionnelles'],
            'prevoyance_garantir_totalite_charges_professionnelles' => ['type' => 'boolean', 'db_field' => 'garantir_totalite_charges_professionnelles'],
            'prevoyance_montant_charges_professionnelles_a_garantir' => ['type' => 'decimal', 'db_field' => 'montant_charges_professionnelles_a_garantir'],
            'prevoyance_duree_indemnisation_souhaitee' => ['type' => 'string', 'db_field' => 'duree_indemnisation_souhaitee'],
            'prevoyance_capital_deces_souhaite' => ['type' => 'decimal', 'db_field' => 'capital_deces_souhaite'],
            'prevoyance_garanties_obseques' => ['type' => 'decimal', 'db_field' => 'garanties_obseques'],
            'prevoyance_rente_enfants' => ['type' => 'decimal', 'db_field' => 'rente_enfants'],
            'prevoyance_rente_conjoint' => ['type' => 'decimal', 'db_field' => 'rente_conjoint'],
            'prevoyance_payeur' => ['type' => 'string', 'db_field' => 'payeur'],
        ],

        // ==================== BAE_RETRAITE (Retirement) ====================
        'bae_retraite' => [
            'retraite_revenus_annuels' => ['type' => 'decimal', 'db_field' => 'revenus_annuels'],
            'retraite_revenus_annuels_foyer' => ['type' => 'decimal', 'db_field' => 'revenus_annuels_foyer'],
            'retraite_impot_revenu' => ['type' => 'decimal', 'db_field' => 'impot_revenu'],
            'retraite_nombre_parts_fiscales' => ['type' => 'decimal', 'db_field' => 'nombre_parts_fiscales'],
            'retraite_tmi' => ['type' => 'string', 'db_field' => 'tmi'],
            'retraite_impot_paye_n_1' => ['type' => 'decimal', 'db_field' => 'impot_paye_n_1'],
            'retraite_age_depart_retraite' => ['type' => 'integer', 'db_field' => 'age_depart_retraite'],
            'retraite_age_depart_retraite_conjoint' => ['type' => 'integer', 'db_field' => 'age_depart_retraite_conjoint'],
            'retraite_pourcentage_revenu_a_maintenir' => ['type' => 'decimal', 'db_field' => 'pourcentage_revenu_a_maintenir'],
            'retraite_contrat_en_place' => ['type' => 'string', 'db_field' => 'contrat_en_place'],
            'retraite_bilan_retraite_disponible' => ['type' => 'boolean', 'db_field' => 'bilan_retraite_disponible'],
            'retraite_complementaire_mise_en_place' => ['type' => 'boolean', 'db_field' => 'complementaire_retraite_mise_en_place'],
            'retraite_designation_etablissement' => ['type' => 'string', 'db_field' => 'designation_etablissement'],
            'retraite_cotisations_annuelles' => ['type' => 'decimal', 'db_field' => 'cotisations_annuelles'],
            'retraite_titulaire' => ['type' => 'string', 'db_field' => 'titulaire'],
        ],

        // ==================== BAE_EPARGNE (Global Savings Overview) ====================
        'bae_epargne' => [
            'epargne_disponible' => ['type' => 'boolean', 'db_field' => 'epargne_disponible'],
            'epargne_montant_disponible' => ['type' => 'decimal', 'db_field' => 'montant_epargne_disponible'],
            'epargne_donation_realisee' => ['type' => 'boolean', 'db_field' => 'donation_realisee'],
            'epargne_donation_forme' => ['type' => 'string', 'db_field' => 'donation_forme'],
            'epargne_donation_date' => ['type' => 'date', 'db_field' => 'donation_date'],
            'epargne_donation_montant' => ['type' => 'decimal', 'db_field' => 'donation_montant'],
            'epargne_donation_beneficiaires' => ['type' => 'string', 'db_field' => 'donation_beneficiaires'],
            'epargne_capacite_estimee' => ['type' => 'decimal', 'db_field' => 'capacite_epargne_estimee'],
            'epargne_estimation_patrimoine' => ['type' => 'decimal', 'db_field' => 'actifs_financiers_total'],
            'epargne_actifs_financiers_pourcentage' => ['type' => 'decimal', 'db_field' => 'actifs_financiers_pourcentage'],
            'epargne_actifs_financiers_total' => ['type' => 'decimal', 'db_field' => 'actifs_financiers_total'],
            'epargne_actifs_financiers_details' => ['type' => 'json', 'db_field' => 'actifs_financiers_details'],
            'epargne_actifs_immo_pourcentage' => ['type' => 'decimal', 'db_field' => 'actifs_immo_pourcentage'],
            'epargne_actifs_immo_total' => ['type' => 'decimal', 'db_field' => 'actifs_immo_total'],
            'epargne_actifs_immo_details' => ['type' => 'json', 'db_field' => 'actifs_immo_details'],
            'epargne_actifs_autres_pourcentage' => ['type' => 'decimal', 'db_field' => 'actifs_autres_pourcentage'],
            'epargne_actifs_autres_total' => ['type' => 'decimal', 'db_field' => 'actifs_autres_total'],
            'epargne_actifs_autres_details' => ['type' => 'json', 'db_field' => 'actifs_autres_details'],
            'epargne_passifs_total_emprunts' => ['type' => 'decimal', 'db_field' => 'passifs_total_emprunts'],
            'epargne_passifs_details' => ['type' => 'json', 'db_field' => 'passifs_details'],
            'epargne_charges_totales' => ['type' => 'decimal', 'db_field' => 'charges_totales'],
            'epargne_charges_details' => ['type' => 'json', 'db_field' => 'charges_details'],
            'epargne_situation_financiere' => ['type' => 'text', 'db_field' => 'situation_financiere_revenus_charges'],
        ],

        // ==================== CLIENT_REVENUS (Income - Multiple) ====================
        'client_revenu' => [
            'revenu_nature' => ['type' => 'string', 'db_field' => 'nature'],
            'revenu_details' => ['type' => 'string', 'db_field' => 'details'],
            'revenu_periodicite' => ['type' => 'string', 'db_field' => 'periodicite'],
            'revenu_montant' => ['type' => 'decimal', 'db_field' => 'montant'],
        ],

        // ==================== CLIENT_ACTIFS_FINANCIERS (Financial Assets - Multiple) ====================
        'client_actif_financier' => [
            'actif_nature' => ['type' => 'string', 'db_field' => 'nature'],
            'actif_etablissement' => ['type' => 'string', 'db_field' => 'etablissement'],
            'actif_detenteur' => ['type' => 'string', 'db_field' => 'detenteur'],
            'actif_date_ouverture' => ['type' => 'date', 'db_field' => 'date_ouverture_souscription'],
            'actif_valeur' => ['type' => 'decimal', 'db_field' => 'valeur_actuelle'],
        ],

        // ==================== CLIENT_BIENS_IMMOBILIERS (Real Estate - Multiple) ====================
        'client_bien_immobilier' => [
            'bien_designation' => ['type' => 'string', 'db_field' => 'designation'],
            'bien_detenteur' => ['type' => 'string', 'db_field' => 'detenteur'],
            'bien_forme_propriete' => ['type' => 'string', 'db_field' => 'forme_propriete'],
            'bien_valeur_actuelle' => ['type' => 'decimal', 'db_field' => 'valeur_actuelle_estimee'],
            'bien_annee_acquisition' => ['type' => 'integer', 'db_field' => 'annee_acquisition'],
            'bien_valeur_acquisition' => ['type' => 'decimal', 'db_field' => 'valeur_acquisition'],
        ],

        // ==================== CLIENT_PASSIFS (Liabilities/Loans - Multiple) ====================
        'client_passif' => [
            'passif_nature' => ['type' => 'string', 'db_field' => 'nature'],
            'passif_preteur' => ['type' => 'string', 'db_field' => 'preteur'],
            'passif_periodicite' => ['type' => 'string', 'db_field' => 'periodicite'],
            'passif_montant_remboursement' => ['type' => 'decimal', 'db_field' => 'montant_remboursement'],
            'passif_capital_restant' => ['type' => 'decimal', 'db_field' => 'capital_restant_du'],
            'passif_duree_restante' => ['type' => 'integer', 'db_field' => 'duree_restante'],
        ],

        // ==================== CLIENT_AUTRES_EPARGNES (Other Savings - Multiple) ====================
        'client_autre_epargne' => [
            'autre_epargne_designation' => ['type' => 'string', 'db_field' => 'designation'],
            'autre_epargne_detenteur' => ['type' => 'string', 'db_field' => 'detenteur'],
            'autre_epargne_valeur' => ['type' => 'decimal', 'db_field' => 'valeur'],
        ],
    ];

    /**
     * Comprehensive alias dictionary for each field
     * Organized by category for better maintenance
     */
    private const FIELD_ALIASES = [
        // ==================== CLIENT - IDENTITY ====================
        'civilite' => [
            'civilite', 'civilité', 'title', 'titre', 'genre', 'sexe',
            'monsieur/madame', 'mr/mme', 'monsieur madame', 'civ',
        ],
        'nom' => [
            'nom', 'name', 'last_name', 'lastname', 'nom de famille',
            'family_name', 'surname', 'nom_client', 'nom client', 'patronyme',
        ],
        'nom_jeune_fille' => [
            'nom_jeune_fille', 'nom jeune fille', 'maiden_name', 'nom de naissance',
            'nom naissance', 'birth_name', 'nom marital',
        ],
        'prenom' => [
            'prenom', 'prénom', 'first_name', 'firstname', 'given_name',
            'prenom_client', 'prénom client', 'prenom1', 'forename',
        ],
        'date_naissance' => [
            'date_naissance', 'date de naissance', 'birth_date', 'birthdate',
            'dob', 'né le', 'naissance', 'dn', 'anniversaire', 'birthday', 'ne le', 'nee le',
        ],
        'lieu_naissance' => [
            'lieu_naissance', 'lieu de naissance', 'birthplace', 'né à',
            'ville naissance', 'birth_city', 'lieu naissance', 'ne a',
        ],
        'nationalite' => [
            'nationalite', 'nationalité', 'nationality', 'nation', 'pays origine',
            'citizenship', 'citoyennete',
        ],

        // ==================== CLIENT - FAMILY ====================
        'situation_matrimoniale' => [
            'situation_matrimoniale', 'situation matrimoniale', 'marital_status',
            'état civil', 'etat civil', 'statut marital', 'sit_fam',
            'situation_familiale', 'situation familiale', 'regime matrimonial',
        ],
        'date_situation_matrimoniale' => [
            'date_situation_matrimoniale', 'date mariage', 'date pacs',
            'date_mariage', 'date union', 'date regime', 'marie depuis',
        ],

        // ==================== CLIENT - PROFESSIONAL ====================
        'situation_actuelle' => [
            'situation_actuelle', 'situation actuelle', 'status', 'statut pro',
            'situation professionnelle', 'actif', 'retraite', 'retraité',
            'situation professionnelle actuelle', 'statut professionnel',
            'emploi actuel', 'activite actuelle', 'en activite',
        ],
        'date_evenement_professionnel' => [
            'date_evenement_professionnel', 'date debut activite', 'date_embauche',
            'date_debut', 'situation professionnelle actuelle depuis le',
            'date entree', 'debut activite', 'depuis le',
        ],
        'profession' => [
            'profession', 'job', 'métier', 'metier', 'occupation', 'emploi',
            'travail', 'activite', 'poste', 'fonction', 'role', 'job_title',
            'intitule poste', 'activite professionnelle',
        ],
        'statut' => [
            'statut', 'statut_pro', 'type_contrat', 'salarie', 'tns',
            'fonctionnaire', 'type emploi', 'contrat', 'regime social', 'csp',
        ],
        'chef_entreprise' => [
            'chef_entreprise', 'chef entreprise', 'entrepreneur', 'dirigeant',
            'ceo', 'gérant', 'gerant', 'chef dentreprise', 'patron', 'president',
        ],
        'travailleur_independant' => [
            'travailleur_independant', 'independant', 'freelance', 'auto_entrepreneur',
            'tns', 'etes vous travailleur independant', 'liberal', 'profession liberale',
        ],
        'mandataire_social' => [
            'mandataire_social', 'mandataire', 'etes vous mandataire social',
            'directeur general', 'dg', 'representant legal',
        ],
        'risques_professionnels' => [
            'risques_professionnels', 'risques pro', 'metier a risque',
            'la profession presente t elle des risques particuliers',
            'risque professionnel', 'travail dangereux',
        ],
        'details_risques_professionnels' => [
            'details_risques_professionnels', 'details risques', 'precision risques',
            'si oui lesquels', 'type risques', 'nature risques',
        ],
        'revenus_annuels' => [
            'revenus_annuels', 'revenus', 'income', 'salaire', 'revenue',
            'revenu annuel', 'salaire_annuel', 'raa', 'revenu', 'salaire annuel',
            'rémunération', 'remuneration', 'revenu brut', 'revenu net',
        ],

        // ==================== CLIENT - CONTACT ====================
        'adresse' => [
            'adresse', 'address', 'rue', 'street', 'domicile', 'adresse1',
            'adresse_postale', 'adresse postale', 'adresse complete', 'voie',
        ],
        'code_postal' => [
            'code_postal', 'code postal', 'cp', 'postal_code', 'zip', 'zipcode',
        ],
        'ville' => [
            'ville', 'city', 'commune', 'localité', 'localite', 'town',
        ],
        'residence_fiscale' => [
            'residence_fiscale', 'résidence fiscale', 'fiscal_residence',
            'pays residence', 'domicile fiscal',
        ],
        'telephone' => [
            'telephone', 'téléphone', 'tel', 'phone', 'mobile', 'portable',
            'numero tel', 'tel_mobile', 'tel_fixe', 'gsm', 'numero telephone',
        ],
        'email' => [
            'email', 'e-mail', 'mail', 'adresse email', 'courriel', 'adresse mail',
        ],

        // ==================== CLIENT - HEALTH/LIFESTYLE ====================
        'fumeur' => [
            'fumeur', 'smoker', 'tabac', 'fume', 'tabagisme', 'etes vous fumeur',
            'consommation tabac', 'cigarette', 'non fumeur',
        ],
        'activites_sportives' => [
            'activites_sportives', 'sport', 'activite sportive', 'pratique_sport',
            'faites vous des activites sportives', 'sports', 'sportif',
        ],
        'niveau_activites_sportives' => [
            'niveau_activites_sportives', 'niveau sport', 'frequence sport',
            'si oui a quel niveau', 'intensite sport', 'competition', 'amateur',
        ],
        'details_activites_sportives' => [
            'details_activites_sportives', 'sports pratiques', 'type sport',
            'si oui quelles activites sportives', 'quels sports',
        ],
        'km_parcourus_annuels' => [
            'km_parcourus_annuels', 'km parcourus', 'kilometres annuels',
            'combien de km faites vous par an', 'km par an', 'kilometrage annuel',
            'distance annuelle', 'km annuel',
        ],

        // ==================== CONJOINT ====================
        'conjoint_nom' => [
            'nom conjoint', 'conjoint nom', 'spouse_name', 'nom epoux',
            'nom épouse', 'nom du conjoint', 'partenaire nom',
        ],
        'conjoint_nom_jeune_fille' => [
            'nom jeune fille conjoint', 'maiden name spouse', 'nom naissance conjoint',
        ],
        'conjoint_prenom' => [
            'prenom conjoint', 'prénom conjoint', 'conjoint prenom',
            'spouse_firstname', 'prenom du conjoint', 'partenaire prenom',
        ],
        'conjoint_date_naissance' => [
            'date naissance conjoint', 'date de naissance conjoint',
            'conjoint date naissance', 'spouse_birthdate', 'dn conjoint',
        ],
        'conjoint_lieu_naissance' => [
            'lieu de naissance conjoint', 'lieu naissance conjoint',
        ],
        'conjoint_nationalite' => [
            'conjoint nationalité', 'nationalite conjoint',
        ],
        'conjoint_telephone' => [
            'conjoint telephone', 'telephone conjoint', 'tel conjoint', 'mobile conjoint',
        ],
        'conjoint_email' => [
            'conjoint email', 'email conjoint', 'mail conjoint',
        ],
        'conjoint_profession' => [
            'profession conjoint', 'conjoint profession', 'metier conjoint',
        ],
        'conjoint_situation_actuelle' => [
            'situation professionnelle actuelle conjoint', 'conjoint situation',
            'statut conjoint', 'situation professionnelle conjoint',
        ],
        'conjoint_fumeur' => [
            'votre conjoint est fumeur', 'conjoint fumeur', 'fumeur conjoint',
        ],
        'conjoint_km_parcourus_annuels' => [
            'km conjoint', 'kilometres conjoint', 'km parcourus conjoint',
            'combien de km faites vous par an votre conjoint',
        ],
        'conjoint_situation_professionnelle' => [
            'situation professionnelle conjoint', 'conjoint situation professionnelle',
            'emploi conjoint', 'travail conjoint',
        ],
        'conjoint_situation_chomage' => [
            'situation chomage conjoint', 'conjoint chomage', 'chomage conjoint',
            'conjoint au chomage',
        ],
        'conjoint_statut' => [
            'statut conjoint', 'conjoint statut', 'statut professionnel conjoint',
            'csp conjoint', 'categorie socio professionnelle conjoint',
        ],
        'conjoint_travailleur_independant' => [
            'conjoint travailleur independant', 'travailleur independant conjoint',
            'conjoint tns', 'tns conjoint', 'conjoint independant',
        ],
        'conjoint_adresse' => [
            'adresse conjoint', 'conjoint adresse', 'domicile conjoint',
        ],
        'conjoint_code_postal' => [
            'code postal conjoint', 'conjoint code postal', 'cp conjoint',
        ],
        'conjoint_ville' => [
            'ville conjoint', 'conjoint ville', 'commune conjoint',
        ],
        'conjoint_activites_sportives' => [
            'activites sportives conjoint', 'conjoint sport', 'sport conjoint',
            'conjoint fait du sport',
        ],
        'conjoint_niveau_activite_sportive' => [
            'niveau sport conjoint', 'conjoint niveau sport', 'intensite sport conjoint',
        ],
        'conjoint_details_activites_sportives' => [
            'sports pratiques conjoint', 'conjoint quels sports', 'type sport conjoint',
        ],
        'conjoint_risques_professionnels' => [
            'risques professionnels conjoint', 'conjoint risques pro',
            'metier a risque conjoint',
        ],
        'conjoint_details_risques_professionnels' => [
            'details risques conjoint', 'precision risques conjoint',
        ],
        'conjoint_date_evenement_professionnel' => [
            'date evenement professionnel conjoint', 'date embauche conjoint',
            'conjoint depuis le',
        ],
        'conjoint_chef_entreprise' => [
            'conjoint chef entreprise', 'chef entreprise conjoint',
            'conjoint dirigeant', 'conjoint gerant',
        ],

        // ==================== ENFANTS ====================
        'enfant1_nom_prenom' => [
            'nom prenom enfant 1', 'nom prénom enfant 1', 'enfant 1',
            'enfant1', 'child 1', 'premier enfant', '1er enfant',
        ],
        'enfant1_date_naissance' => [
            'date de naissance enfant 1', 'date naissance enfant 1', 'dn enfant 1',
        ],
        'enfant2_nom_prenom' => [
            'nom prenom enfant 2', 'nom prénom enfant 2', 'enfant 2',
            'enfant2', 'child 2', 'deuxieme enfant', '2eme enfant',
        ],
        'enfant2_date_naissance' => [
            'date de naissance enfant 2', 'date naissance enfant 2', 'dn enfant 2',
        ],
        'enfant3_nom_prenom' => [
            'nom prenom enfant 3', 'nom prénom enfant 3', 'enfant 3',
            'enfant3', 'child 3', 'troisieme enfant', '3eme enfant',
        ],
        'enfant3_date_naissance' => [
            'date de naissance enfant 3', 'date naissance enfant 3', 'dn enfant 3',
        ],
        'enfant4_nom_prenom' => [
            'nom prenom enfant 4', 'nom prénom enfant 4', 'enfant 4',
            'enfant4', 'child 4', 'quatrieme enfant', '4eme enfant',
        ],
        'enfant4_date_naissance' => [
            'date de naissance enfant 4', 'date naissance enfant 4', 'dn enfant 4',
        ],
        'nombre_enfants' => [
            'nombre enfant', 'nombre denfant a charge', 'nb enfants',
            'enfants', 'nombre_enfants', 'nb_enfants', 'nbre enfants',
            'combien enfants', 'enfants a charge',
        ],

        // ==================== SANTE/MUTUELLE ====================
        'sante_contrat_en_place' => [
            'sante contrat en place', 'mutuelle en place', 'contrat sante',
            'mutuelle actuelle', 'complementaire sante', 'assurance sante',
        ],
        'sante_budget_mensuel_maximum' => [
            'sante budget mensuel', 'budget mutuelle', 'budget sante',
            'budget mensuel maximum sante', 'cotisation mutuelle souhaitee',
        ],
        'sante_niveau_hospitalisation' => [
            'niveau hospitalisation', 'hospitalisation', 'couverture hospitalisation',
        ],
        'sante_niveau_chambre_particuliere' => [
            'niveau chambre particuliere', 'chambre particuliere', 'chambre individuelle',
        ],
        'sante_niveau_medecin_generaliste' => [
            'niveau medecin generaliste', 'medecin generaliste', 'consultation generaliste',
        ],
        'sante_niveau_analyses_imagerie' => [
            'niveau analyses imagerie', 'analyses', 'imagerie', 'radiologie',
        ],
        'sante_niveau_auxiliaires_medicaux' => [
            'niveau auxiliaires medicaux', 'auxiliaires medicaux', 'kine', 'osteo',
        ],
        'sante_niveau_pharmacie' => [
            'niveau pharmacie', 'pharmacie', 'medicaments',
        ],
        'sante_niveau_dentaire' => [
            'niveau dentaire', 'dentaire', 'soins dentaires', 'dentiste',
        ],
        'sante_niveau_optique' => [
            'niveau optique', 'optique', 'lunettes', 'ophtalmologie',
        ],
        'sante_niveau_protheses_auditives' => [
            'niveau protheses auditives', 'protheses auditives', 'audioprothese',
        ],

        // ==================== PREVOYANCE ====================
        'prevoyance_contrat_en_place' => [
            'prevoyance contrat en place', 'contrat prevoyance', 'prevoyance actuelle',
            'assurance prevoyance', 'couverture prevoyance',
        ],
        'prevoyance_date_effet' => [
            'prevoyance date effet', 'date effet prevoyance', 'debut contrat prevoyance',
        ],
        'prevoyance_cotisations' => [
            'prevoyance cotisations', 'cotisations prevoyance', 'prime prevoyance',
        ],
        'prevoyance_souhaite_couverture_invalidite' => [
            'souhaite couverture invalidite', 'couverture invalidite', 'invalidite',
            'garantie invalidite', 'incapacite', 'arret travail',
        ],
        'prevoyance_revenu_a_garantir' => [
            'revenu a garantir', 'revenu garanti', 'indemnites journalieres',
            'ij', 'maintien salaire',
        ],
        'prevoyance_souhaite_couvrir_charges_professionnelles' => [
            'couvrir charges professionnelles', 'charges professionnelles',
            'frais professionnels', 'garantie charges pro',
        ],
        'prevoyance_montant_annuel_charges_professionnelles' => [
            'montant annuel charges professionnelles', 'charges pro annuelles',
            'frais pro annuels',
        ],
        'prevoyance_capital_deces_souhaite' => [
            'capital deces souhaite', 'capital deces', 'garantie deces',
            'assurance deces', 'capital en cas de deces',
        ],
        'prevoyance_garanties_obseques' => [
            'garanties obseques', 'obseques', 'assurance obseques', 'capital obseques',
        ],
        'prevoyance_rente_enfants' => [
            'rente enfants', 'rente education', 'rente orphelin',
        ],
        'prevoyance_rente_conjoint' => [
            'rente conjoint', 'rente veuvage', 'pension conjoint survivant',
        ],

        // ==================== RETRAITE ====================
        'retraite_revenus_annuels' => [
            'retraite revenus annuels', 'revenus annuels retraite',
            'revenus actuels pour retraite',
        ],
        'retraite_revenus_annuels_foyer' => [
            'retraite revenus annuels foyer', 'revenus foyer fiscal',
            'revenus annuels foyer fiscal', 'revenus menage',
        ],
        'retraite_impot_revenu' => [
            'retraite impot revenu', 'impot sur le revenu', 'ir', 'impot revenu',
        ],
        'retraite_nombre_parts_fiscales' => [
            'nombre parts fiscales', 'parts fiscales', 'quotient familial',
        ],
        'retraite_tmi' => [
            'tmi', 'tranche marginale imposition', 'tranche impot',
            'taux marginal imposition',
        ],
        'retraite_impot_paye_n_1' => [
            'impot paye n 1', 'impot annee precedente', 'dernier impot paye',
        ],
        'retraite_age_depart_retraite' => [
            'age depart retraite', 'age retraite', 'depart retraite',
            'a quel age comptez vous partir', 'age du depart a la retraite',
        ],
        'retraite_age_depart_retraite_conjoint' => [
            'age depart retraite conjoint', 'age retraite conjoint',
            'depart retraite conjoint',
        ],
        'retraite_pourcentage_revenu_a_maintenir' => [
            'pourcentage revenu a maintenir', 'taux remplacement',
            'revenu a maintenir retraite', 'objectif retraite',
        ],
        'retraite_contrat_en_place' => [
            'retraite contrat en place', 'contrat retraite', 'per', 'perp',
            'madelin retraite', 'epargne retraite',
        ],
        'retraite_bilan_retraite_disponible' => [
            'bilan retraite disponible', 'bilan retraite', 'estimation retraite',
            'releve carriere',
        ],
        'retraite_complementaire_mise_en_place' => [
            'complementaire retraite mise en place', 'complementaire retraite',
            'retraite supplementaire', 'article 83', 'pere',
        ],
        'retraite_designation_etablissement' => [
            'retraite designation etablissement', 'etablissement retraite',
            'organisme retraite', 'assureur retraite',
        ],
        'retraite_cotisations_annuelles' => [
            'retraite cotisations annuelles', 'cotisations retraite',
            'versements retraite', 'prime retraite',
        ],
        'retraite_titulaire' => [
            'retraite titulaire', 'titulaire contrat retraite',
        ],

        // ==================== EPARGNE GLOBALE ====================
        'epargne_disponible' => [
            'epargne disponible', 'dispose epargne', 'a une epargne',
            'le client dispose t il d une epargne disponible',
        ],
        'epargne_montant_disponible' => [
            'montant epargne disponible', 'epargne liquide', 'liquidites',
            'tresorerie disponible',
        ],
        'epargne_donation_realisee' => [
            'donation realisee', 'a fait une donation', 'donation effectuee',
        ],
        'epargne_donation_forme' => [
            'donation forme', 'forme donation', 'type donation',
            'epargne donation forme',
        ],
        'epargne_donation_date' => [
            'donation date', 'date donation', 'quand donation',
        ],
        'epargne_donation_montant' => [
            'donation montant', 'montant donation', 'valeur donation',
        ],
        'epargne_donation_beneficiaires' => [
            'donation beneficiaires', 'beneficiaires donation', 'donataires',
        ],
        'epargne_capacite_estimee' => [
            'capacite epargne estimee', 'capacite epargne', 'epargne mensuelle',
            'effort epargne', 'combien pouvez vous epargner',
        ],
        'epargne_estimation_patrimoine' => [
            'estimation patrimoine', 'patrimoine global', 'patrimoine total',
            'estimation globale du patrimoine', 'valeur patrimoine',
        ],
        'epargne_actifs_financiers_total' => [
            'actifs financiers total', 'total actifs financiers',
            'patrimoine financier', 'epargne financiere totale',
        ],
        'epargne_actifs_immo_total' => [
            'actifs immo total', 'total actifs immobiliers',
            'patrimoine immobilier', 'valeur immobilier',
        ],
        'epargne_passifs_total_emprunts' => [
            'passifs total emprunts', 'total emprunts', 'encours credit',
            'dettes totales', 'endettement total',
        ],
        'epargne_charges_totales' => [
            'charges totales', 'total charges', 'charges annuelles',
            'depenses fixes',
        ],
        'epargne_actifs_financiers_pourcentage' => [
            'pourcentage actifs financiers', 'part actifs financiers',
            'repartition actifs financiers', 'poids financiers',
        ],
        'epargne_actifs_financiers_details' => [
            'details actifs financiers', 'detail placements', 'liste actifs financiers',
        ],
        'epargne_actifs_immo_pourcentage' => [
            'pourcentage actifs immobiliers', 'part immobilier',
            'repartition immobilier', 'poids immobilier',
        ],
        'epargne_actifs_immo_details' => [
            'details actifs immobiliers', 'detail biens', 'liste biens immobiliers',
        ],
        'epargne_actifs_autres_pourcentage' => [
            'pourcentage autres actifs', 'part autres actifs',
            'repartition autres actifs',
        ],
        'epargne_actifs_autres_total' => [
            'total autres actifs', 'autres actifs total', 'valeur autres actifs',
        ],
        'epargne_actifs_autres_details' => [
            'details autres actifs', 'autres placements details',
        ],
        'epargne_passifs_details' => [
            'details passifs', 'details emprunts', 'liste emprunts',
        ],
        'epargne_charges_details' => [
            'details charges', 'liste charges', 'decomposition charges',
        ],
        'epargne_situation_financiere' => [
            'situation financiere', 'revenus moins charges', 'bilan financier',
            'situation revenus charges', 'equilibre financier',
        ],

        // ==================== REVENUS (Multiple) ====================
        'revenu_nature' => [
            'nature revenu', 'type revenu', 'source revenu', 'origine revenu',
            'categorie revenu',
        ],
        'revenu_details' => [
            'details revenu', 'precision revenu', 'commentaire revenu',
        ],
        'revenu_periodicite' => [
            'periodicite revenu', 'frequence revenu', 'mensuel', 'annuel',
        ],
        'revenu_montant' => [
            'montant revenu', 'valeur revenu', 'somme revenu',
        ],

        // ==================== ACTIFS FINANCIERS (Multiple) ====================
        'actif_nature' => [
            'nature actif', 'type actif', 'type placement', 'nature placement',
            'type epargne', 'produit financier', 'designation actif',
            'type de placement', 'nature du placement',
        ],
        'actif_etablissement' => [
            'etablissement actif', 'banque actif', 'organisme', 'assureur',
            'compagnie', 'etablissement', 'aupres de quel etablissement',
        ],
        'actif_detenteur' => [
            'detenteur actif', 'titulaire actif', 'proprietaire actif',
            'au nom de', 'detenteur', 'titulaire',
        ],
        'actif_date_ouverture' => [
            'date ouverture', 'date souscription', 'ouvert le', 'souscrit le',
            'date creation compte', 'date de souscription',
        ],
        'actif_valeur' => [
            'valeur actif', 'montant actif', 'solde', 'encours',
            'valeur actuelle', 'valorisation', 'valeur de rachat',
        ],

        // ==================== BIENS IMMOBILIERS (Multiple) ====================
        'bien_designation' => [
            'designation bien', 'type bien', 'nature bien', 'bien immobilier',
            'type immobilier', 'residence principale', 'residence secondaire',
            'investissement locatif',
        ],
        'bien_detenteur' => [
            'detenteur bien', 'proprietaire bien', 'titulaire bien',
        ],
        'bien_forme_propriete' => [
            'forme propriete', 'type propriete', 'regime propriete',
            'pleine propriete', 'usufruit', 'nue propriete', 'indivision',
        ],
        'bien_valeur_actuelle' => [
            'valeur bien', 'estimation bien', 'valeur actuelle bien',
            'prix actuel', 'valeur estimee', 'valeur venale',
        ],
        'bien_annee_acquisition' => [
            'annee acquisition', 'date achat', 'annee achat', 'acquis en',
        ],
        'bien_valeur_acquisition' => [
            'valeur acquisition', 'prix achat', 'prix acquisition', 'cout achat',
        ],

        // ==================== PASSIFS/EMPRUNTS (Multiple) ====================
        'passif_nature' => [
            'nature emprunt', 'type emprunt', 'type pret', 'nature pret',
            'credit', 'type credit', 'objet pret', 'pret immobilier',
            'pret consommation', 'credit auto',
        ],
        'passif_preteur' => [
            'preteur', 'banque pret', 'organisme pret', 'etablissement pret',
            'creancier', 'banque', 'organisme preteur',
        ],
        'passif_periodicite' => [
            'periodicite remboursement', 'frequence pret', 'echeance',
            'mensualite', 'type echeance',
        ],
        'passif_montant_remboursement' => [
            'montant remboursement', 'mensualite', 'echeance mensuelle',
            'remboursement mensuel', 'montant echeance',
        ],
        'passif_capital_restant' => [
            'capital restant', 'crd', 'capital restant du', 'solde emprunt',
            'reste a payer', 'encours pret',
        ],
        'passif_duree_restante' => [
            'duree restante', 'mois restants', 'echeances restantes',
            'fin emprunt', 'terme pret',
        ],

        // ==================== AUTRES EPARGNES (Multiple) ====================
        'autre_epargne_designation' => [
            'designation epargne', 'type epargne', 'nature epargne',
            'autre epargne', 'autre placement',
        ],
        'autre_epargne_detenteur' => [
            'detenteur epargne', 'titulaire epargne',
        ],
        'autre_epargne_valeur' => [
            'valeur epargne', 'montant epargne', 'solde epargne',
        ],
    ];

    /**
     * Semantic groups for contextual matching
     */
    private const SEMANTIC_GROUPS = [
        'identity' => ['nom', 'prenom', 'civilite', 'nom_jeune_fille', 'naissance'],
        'contact' => ['telephone', 'email', 'adresse', 'code_postal', 'ville'],
        'professional' => ['profession', 'situation', 'statut', 'revenus', 'entreprise', 'travail', 'emploi'],
        'family' => ['matrimonial', 'conjoint', 'enfant', 'mariage', 'pacs', 'famille'],
        'health' => ['fumeur', 'sport', 'sante', 'mutuelle', 'medical', 'hospitalisation', 'dentaire', 'optique'],
        'prevoyance' => ['prevoyance', 'invalidite', 'deces', 'incapacite', 'obseques', 'rente', 'capital'],
        'retraite' => ['retraite', 'pension', 'per', 'perp', 'madelin', 'trimestre', 'carriere'],
        'epargne' => ['epargne', 'placement', 'actif', 'patrimoine', 'donation', 'investissement'],
        'immobilier' => ['bien', 'immobilier', 'propriete', 'residence', 'logement', 'appartement', 'maison'],
        'credit' => ['passif', 'emprunt', 'pret', 'credit', 'remboursement', 'mensualite', 'capital restant'],
        'fiscal' => ['impot', 'tmi', 'fiscal', 'parts', 'quotient'],
    ];

    private const SCORING_WEIGHTS = [
        'exact_match' => 1.0,
        'alias_exact' => 0.95,
        'contains_full' => 0.85,
        'levenshtein' => 0.75,
        'similar_text' => 0.65,
        'word_overlap' => 0.55,
        'semantic' => 0.45,
    ];

    private const MIN_CONFIDENCE = 0.55;

    public function suggestMappings(array $sourceColumns): array
    {
        $suggestions = [];
        $allFields = $this->getAllTargetFields();

        foreach ($sourceColumns as $sourceColumn) {
            $bestMatch = null;
            $bestScore = 0;
            $allScores = [];

            foreach ($allFields as $targetField) {
                $score = $this->calculateMatchScore($sourceColumn, $targetField);

                if ($score > 0.3) {
                    $allScores[$targetField] = $score;
                }

                if ($score > $bestScore && $score >= self::MIN_CONFIDENCE) {
                    $bestScore = $score;
                    $bestMatch = $targetField;
                }
            }

            arsort($allScores);
            $alternatives = array_slice($allScores, 0, 5, true);

            $suggestions[$sourceColumn] = [
                'suggested_field' => $bestMatch,
                'confidence' => round($bestScore, 3),
                'source_column' => $sourceColumn,
                'alternatives' => $alternatives,
            ];
        }

        return $suggestions;
    }

    private function calculateMatchScore(string $sourceColumn, string $targetField): float
    {
        $normalizedSource = $this->normalizeForMatching($sourceColumn);
        $normalizedTarget = $this->normalizeForMatching($targetField);

        $scores = [];

        if ($normalizedSource === $normalizedTarget) {
            return self::SCORING_WEIGHTS['exact_match'];
        }

        $aliases = self::FIELD_ALIASES[$targetField] ?? [$targetField];
        foreach ($aliases as $index => $alias) {
            $normalizedAlias = $this->normalizeForMatching($alias);

            if ($normalizedSource === $normalizedAlias) {
                $positionBonus = max(0, 0.04 - ($index * 0.003));
                return self::SCORING_WEIGHTS['alias_exact'] + $positionBonus;
            }

            if (strlen($normalizedAlias) >= 4 && strlen($normalizedSource) >= 4) {
                if (str_contains($normalizedSource, $normalizedAlias)) {
                    $ratio = strlen($normalizedAlias) / strlen($normalizedSource);
                    $scores[] = self::SCORING_WEIGHTS['contains_full'] * $ratio;
                }
                if (str_contains($normalizedAlias, $normalizedSource)) {
                    $ratio = strlen($normalizedSource) / strlen($normalizedAlias);
                    $scores[] = self::SCORING_WEIGHTS['contains_full'] * $ratio;
                }
            }

            $levenshtein = levenshtein($normalizedSource, $normalizedAlias);
            $maxLen = max(strlen($normalizedSource), strlen($normalizedAlias));
            if ($maxLen > 0) {
                $levenshteinScore = 1 - ($levenshtein / $maxLen);
                if ($levenshteinScore > 0.65) {
                    $scores[] = self::SCORING_WEIGHTS['levenshtein'] * $levenshteinScore;
                }
            }

            similar_text($normalizedSource, $normalizedAlias, $percent);
            if ($percent > 65) {
                $scores[] = self::SCORING_WEIGHTS['similar_text'] * ($percent / 100);
            }

            $wordOverlapScore = $this->calculateWordOverlap($normalizedSource, $normalizedAlias);
            if ($wordOverlapScore > 0.4) {
                $scores[] = self::SCORING_WEIGHTS['word_overlap'] * $wordOverlapScore;
            }
        }

        $semanticScore = $this->calculateSemanticScore($normalizedSource, $targetField);
        if ($semanticScore > 0) {
            $scores[] = self::SCORING_WEIGHTS['semantic'] * $semanticScore;
        }

        return !empty($scores) ? max($scores) : 0;
    }

    private function calculateWordOverlap(string $str1, string $str2): float
    {
        $words1 = array_filter(preg_split('/[\s_]+/', $str1));
        $words2 = array_filter(preg_split('/[\s_]+/', $str2));

        if (empty($words1) || empty($words2)) {
            return 0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    private function calculateSemanticScore(string $normalizedSource, string $targetField): float
    {
        $targetGroup = null;
        foreach (self::SEMANTIC_GROUPS as $group => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($targetField, $keyword)) {
                    $targetGroup = $group;
                    break 2;
                }
            }
        }

        if (!$targetGroup) {
            return 0;
        }

        $keywords = self::SEMANTIC_GROUPS[$targetGroup];
        $matchCount = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($normalizedSource, $keyword)) {
                $matchCount++;
            }
        }

        return $matchCount > 0 ? min(1, $matchCount / 2) : 0;
    }

    private function normalizeForMatching(string $value): string
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $normalized = preg_replace('/[^a-z0-9\s_]/', '', $normalized);
        $normalized = preg_replace('/[\s_]+/', ' ', $normalized);

        return trim($normalized);
    }

    private function getAllTargetFields(): array
    {
        $fields = [];

        foreach (self::DATABASE_SCHEMA as $table => $tableFields) {
            foreach ($tableFields as $field => $config) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getDatabaseSchema(): array
    {
        return self::DATABASE_SCHEMA;
    }

    public function applyMapping(array $rawData, array $columnMappings): array
    {
        $mappedData = [];

        foreach ($columnMappings as $sourceColumn => $targetField) {
            if (empty($targetField) || !isset($rawData[$sourceColumn])) {
                continue;
            }

            $value = $rawData[$sourceColumn];
            $tableInfo = $this->getFieldTableInfo($targetField);

            switch ($tableInfo['table']) {
                case 'conjoint':
                    if (!isset($mappedData['conjoint'])) {
                        $mappedData['conjoint'] = [];
                    }
                    $dbField = $tableInfo['db_field'] ?? str_replace('conjoint_', '', $targetField);
                    $mappedData['conjoint'][$dbField] = $value;
                    break;

                case 'enfant':
                    $index = $tableInfo['index'] ?? 1;
                    $enfantIndex = $index - 1;
                    if (!isset($mappedData['enfants'])) {
                        $mappedData['enfants'] = [];
                    }
                    if (!isset($mappedData['enfants'][$enfantIndex])) {
                        $mappedData['enfants'][$enfantIndex] = [];
                    }
                    $dbField = $tableInfo['db_field'] ?? preg_replace('/^enfant\d+_/', '', $targetField);
                    $mappedData['enfants'][$enfantIndex][$dbField] = $value;
                    break;

                case 'sante_souhaits':
                case 'bae_prevoyance':
                case 'bae_retraite':
                case 'bae_epargne':
                case 'client_revenu':
                case 'client_actif_financier':
                case 'client_bien_immobilier':
                case 'client_passif':
                case 'client_autre_epargne':
                    $tableName = $tableInfo['table'];
                    if (!isset($mappedData["_{$tableName}"])) {
                        $mappedData["_{$tableName}"] = [];
                    }
                    $dbField = $tableInfo['db_field'] ?? $targetField;
                    $mappedData["_{$tableName}"][$dbField] = $value;
                    break;

                default:
                    if ($targetField === 'nombre_enfants') {
                        $mappedData['_nombre_enfants'] = $value;
                    } else {
                        $mappedData[$targetField] = $value;
                    }
            }
        }

        return $mappedData;
    }

    private function getFieldTableInfo(string $targetField): array
    {
        foreach (self::DATABASE_SCHEMA as $table => $fields) {
            if (isset($fields[$targetField])) {
                $fieldConfig = $fields[$targetField];
                return [
                    'table' => $table,
                    'db_field' => $fieldConfig['db_field'] ?? $targetField,
                    'type' => $fieldConfig['type'] ?? 'string',
                    'index' => $fieldConfig['index'] ?? null,
                ];
            }
        }

        return ['table' => 'client', 'db_field' => $targetField, 'type' => 'string'];
    }

    public function createMapping(int $teamId, string $name, string $sourceType, array $columnMappings, ?array $defaultValues = null): ImportMapping
    {
        return ImportMapping::create([
            'team_id' => $teamId,
            'name' => $name,
            'source_type' => $sourceType,
            'column_mappings' => $columnMappings,
            'default_values' => $defaultValues,
        ]);
    }

    public function updateMapping(ImportMapping $mapping, array $data): ImportMapping
    {
        $mapping->update($data);
        return $mapping->fresh();
    }

    public function getTeamMappings(int $teamId): \Illuminate\Database\Eloquent\Collection
    {
        return ImportMapping::where('team_id', $teamId)
            ->orderBy('name')
            ->get();
    }

    public function getAvailableTargetFields(): array
    {
        $result = [];

        foreach (self::DATABASE_SCHEMA as $table => $fields) {
            $result[$table] = array_keys($fields);
        }

        return $result;
    }

    /**
     * Retourne les champs avec labels français pour le frontend
     * Utilise le cache pour éviter les problèmes de mémoire
     */
    public function getEnhancedFieldsList(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('import_enhanced_fields', 3600, function () {
            return $this->buildEnhancedFieldsList();
        });
    }

    /**
     * Construit la liste des champs enrichis
     */
    private function buildEnhancedFieldsList(): array
    {
        $tableLabels = [
            'client' => 'Client',
            'conjoint' => 'Conjoint',
            'enfant' => 'Enfants',
            'sante_souhaits' => 'Santé / Mutuelle',
            'bae_prevoyance' => 'Prévoyance',
            'bae_retraite' => 'Retraite',
            'bae_epargne' => 'Épargne',
            'client_revenu' => 'Revenus',
            'client_actif_financier' => 'Actifs Financiers',
            'client_bien_immobilier' => 'Biens Immobiliers',
            'client_passif' => 'Passifs / Emprunts',
            'client_autre_epargne' => 'Autres Épargnes',
            'entreprise' => 'Entreprise',
            'questionnaire_risque' => 'Questionnaire Risque',
        ];

        $result = [];

        foreach (self::DATABASE_SCHEMA as $table => $fields) {
            $groupLabel = $tableLabels[$table] ?? ucfirst(str_replace('_', ' ', $table));

            foreach ($fields as $fieldKey => $fieldConfig) {
                // Extraire l'index si présent (enfant1_nom -> index=1)
                $index = null;
                if (preg_match('/(\d+)_/', $fieldKey, $matches)) {
                    $index = (int) $matches[1];
                }

                // Générer un label lisible à partir du nom de champ
                $label = $this->generateFieldLabel($fieldKey, $index);

                $result[] = [
                    'value' => $fieldKey,
                    'label' => $label,
                    'group' => $groupLabel,
                    'table' => $table,
                    'index' => $index,
                ];
            }
        }

        return $result;
    }

    /**
     * Génère un label français lisible pour un champ
     */
    private function generateFieldLabel(string $fieldKey, ?int $index = null): string
    {
        // Labels manuels pour les champs courants
        static $labels = [
            'civilite' => 'Civilité', 'nom' => 'Nom', 'prenom' => 'Prénom',
            'date_naissance' => 'Date de naissance', 'lieu_naissance' => 'Lieu de naissance',
            'nationalite' => 'Nationalité', 'adresse' => 'Adresse', 'code_postal' => 'Code postal',
            'ville' => 'Ville', 'telephone' => 'Téléphone', 'email' => 'Email',
            'profession' => 'Profession', 'statut' => 'Statut', 'revenus_annuels' => 'Revenus annuels',
            'fumeur' => 'Fumeur', 'activites_sportives' => 'Activités sportives',
            'chef_entreprise' => "Chef d'entreprise", 'travailleur_independant' => 'Travailleur indépendant',
            'situation_matrimoniale' => 'Situation matrimoniale', 'residence_fiscale' => 'Résidence fiscale',
            'nom_jeune_fille' => 'Nom de jeune fille', 'mandataire_social' => 'Mandataire social',
            'nature' => 'Nature', 'periodicite' => 'Périodicité', 'montant' => 'Montant',
            'details' => 'Détails', 'etablissement' => 'Établissement', 'detenteur' => 'Détenteur',
            'valeur_actuelle' => 'Valeur actuelle', 'designation' => 'Désignation',
            'forme_propriete' => 'Forme de propriété', 'preteur' => 'Prêteur',
            'capital_restant_du' => 'Capital restant dû', 'duree_restante' => 'Durée restante',
            'fiscalement_a_charge' => 'Fiscalement à charge', 'garde_alternee' => 'Garde alternée',
            'contrat_en_place' => 'Contrat en place', 'budget_mensuel_maximum' => 'Budget mensuel max',
            'niveau_hospitalisation' => 'Niveau hospitalisation', 'niveau_dentaire' => 'Niveau dentaire',
            'niveau_optique' => 'Niveau optique', 'age_depart_retraite' => 'Âge départ retraite',
            'tmi' => 'TMI', 'nombre_parts_fiscales' => 'Nombre parts fiscales',
            'valeur' => 'Valeur', 'nom_prenom' => 'Nom et Prénom', 'nombre_enfants' => "Nombre d'enfants",
        ];

        // Enlever le préfixe indexé (enfant1_, revenu2_, etc.)
        $cleanKey = preg_replace('/^([a-z_]+)\d+_/', '', $fieldKey);

        // Enlever le préfixe de table (conjoint_, sante_, etc.)
        $shortKey = preg_replace('/^(conjoint|sante|prevoyance|retraite|epargne|actif|bien_immo|passif|autre_epargne|entreprise|risque)_/', '', $cleanKey);

        // Chercher un label
        $label = $labels[$fieldKey] ?? $labels[$cleanKey] ?? $labels[$shortKey] ?? null;

        if (!$label) {
            // Générer automatiquement: snake_case -> Title Case
            $label = ucfirst(str_replace('_', ' ', $shortKey));
        }

        // Ajouter le préfixe pour les champs conjoint
        if (str_starts_with($fieldKey, 'conjoint_')) {
            $label .= ' (conjoint)';
        }

        // Ajouter l'index
        if ($index !== null) {
            $label .= " #{$index}";
        }

        return $label;
    }

    public function validateMapping(array $columnMappings): array
    {
        $errors = [];
        $allFields = $this->getAllTargetFields();

        foreach ($columnMappings as $source => $target) {
            if (!empty($target) && !in_array($target, $allFields)) {
                $errors[] = "Champ cible invalide: {$target} pour la colonne {$source}";
            }
        }

        return $errors;
    }

    public function getFieldAliases(string $field): array
    {
        return self::FIELD_ALIASES[$field] ?? [$field];
    }
}
