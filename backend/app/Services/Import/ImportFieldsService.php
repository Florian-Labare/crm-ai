<?php

namespace App\Services\Import;

use App\Models\BaeEpargne;
use App\Models\BaePrevoyance;
use App\Models\BaeRetraite;
use App\Models\Client;
use App\Models\ClientActifFinancier;
use App\Models\ClientAutreEpargne;
use App\Models\ClientBienImmobilier;
use App\Models\ClientPassif;
use App\Models\ClientRevenu;
use App\Models\Conjoint;
use App\Models\Enfant;
use App\Models\Entreprise;
use App\Models\QuestionnaireRisque;
use App\Models\SanteSouhait;

class ImportFieldsService
{
    /**
     * Configuration des tables liées au client avec leurs métadonnées
     */
    private const TABLE_CONFIG = [
        'client' => [
            'model' => Client::class,
            'label' => 'Client',
            'relation' => 'principal',
            'multiple' => false,
        ],
        'conjoint' => [
            'model' => Conjoint::class,
            'label' => 'Conjoint',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'conjoint_',
        ],
        'enfant' => [
            'model' => Enfant::class,
            'label' => 'Enfants',
            'relation' => 'hasMany',
            'multiple' => true,
            'max_items' => 10,
            'prefix' => 'enfant{n}_',
        ],
        'sante_souhaits' => [
            'model' => SanteSouhait::class,
            'label' => 'Santé / Mutuelle',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'sante_',
        ],
        'bae_prevoyance' => [
            'model' => BaePrevoyance::class,
            'label' => 'Prévoyance',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'prevoyance_',
        ],
        'bae_retraite' => [
            'model' => BaeRetraite::class,
            'label' => 'Retraite',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'retraite_',
        ],
        'bae_epargne' => [
            'model' => BaeEpargne::class,
            'label' => 'Épargne',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'epargne_',
        ],
        'client_revenu' => [
            'model' => ClientRevenu::class,
            'label' => 'Revenus',
            'relation' => 'hasMany',
            'multiple' => true,
            'max_items' => 5,
            'prefix' => 'revenu{n}_',
        ],
        'client_actif_financier' => [
            'model' => ClientActifFinancier::class,
            'label' => 'Actifs Financiers',
            'relation' => 'hasMany',
            'multiple' => true,
            'max_items' => 10,
            'prefix' => 'actif{n}_',
        ],
        'client_bien_immobilier' => [
            'model' => ClientBienImmobilier::class,
            'label' => 'Biens Immobiliers',
            'relation' => 'hasMany',
            'multiple' => true,
            'max_items' => 10,
            'prefix' => 'bien_immo{n}_',
        ],
        'client_passif' => [
            'model' => ClientPassif::class,
            'label' => 'Passifs / Emprunts',
            'relation' => 'hasMany',
            'multiple' => true,
            'max_items' => 10,
            'prefix' => 'passif{n}_',
        ],
        'client_autre_epargne' => [
            'model' => ClientAutreEpargne::class,
            'label' => 'Autres Épargnes',
            'relation' => 'hasMany',
            'multiple' => true,
            'max_items' => 5,
            'prefix' => 'autre_epargne{n}_',
        ],
        'entreprise' => [
            'model' => Entreprise::class,
            'label' => 'Entreprise',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'entreprise_',
        ],
        'questionnaire_risque' => [
            'model' => QuestionnaireRisque::class,
            'label' => 'Questionnaire Risque',
            'relation' => 'hasOne',
            'multiple' => false,
            'prefix' => 'risque_',
        ],
    ];

    /**
     * Labels français pour les champs
     */
    private const FIELD_LABELS = [
        // Client
        'civilite' => 'Civilité',
        'nom' => 'Nom',
        'nom_jeune_fille' => 'Nom de jeune fille',
        'prenom' => 'Prénom',
        'date_naissance' => 'Date de naissance',
        'lieu_naissance' => 'Lieu de naissance',
        'nationalite' => 'Nationalité',
        'situation_matrimoniale' => 'Situation matrimoniale',
        'date_situation_matrimoniale' => 'Date situation matrimoniale',
        'situation_actuelle' => 'Situation actuelle',
        'profession' => 'Profession',
        'date_evenement_professionnel' => 'Date événement professionnel',
        'risques_professionnels' => 'Risques professionnels',
        'details_risques_professionnels' => 'Détails risques professionnels',
        'revenus_annuels' => 'Revenus annuels',
        'adresse' => 'Adresse',
        'code_postal' => 'Code postal',
        'ville' => 'Ville',
        'residence_fiscale' => 'Résidence fiscale',
        'telephone' => 'Téléphone',
        'email' => 'Email',
        'fumeur' => 'Fumeur',
        'activites_sportives' => 'Activités sportives',
        'details_activites_sportives' => 'Détails activités sportives',
        'niveau_activites_sportives' => 'Niveau activités sportives',
        'chef_entreprise' => 'Chef d\'entreprise',
        'travailleur_independant' => 'Travailleur indépendant',
        'mandataire_social' => 'Mandataire social',
        'statut' => 'Statut',

        // Conjoint (noms de colonnes spécifiques)
        'datedenaissance' => 'Date de naissance',
        'lieudenaissance' => 'Lieu de naissance',
        'situation_professionnelle' => 'Situation professionnelle',
        'situation_chomage' => 'Situation chômage',
        'situation_actuelle_statut' => 'Situation actuelle',
        'km_parcourus_annuels' => 'Km parcourus annuels',
        'niveau_activite_sportive' => 'Niveau activité sportive',

        // Enfant
        'fiscalement_a_charge' => 'Fiscalement à charge',
        'garde_alternee' => 'Garde alternée',

        // Santé
        'contrat_en_place' => 'Contrat en place',
        'budget_mensuel_maximum' => 'Budget mensuel maximum',
        'niveau_hospitalisation' => 'Niveau hospitalisation',
        'niveau_chambre_particuliere' => 'Niveau chambre particulière',
        'niveau_medecin_generaliste' => 'Niveau médecin généraliste',
        'niveau_analyses_imagerie' => 'Niveau analyses/imagerie',
        'niveau_auxiliaires_medicaux' => 'Niveau auxiliaires médicaux',
        'niveau_pharmacie' => 'Niveau pharmacie',
        'niveau_dentaire' => 'Niveau dentaire',
        'niveau_optique' => 'Niveau optique',
        'niveau_protheses_auditives' => 'Niveau prothèses auditives',
        'souhaite_medecine_douce' => 'Souhaite médecine douce',
        'souhaite_cures_thermales' => 'Souhaite cures thermales',
        'souhaite_autres_protheses' => 'Souhaite autres prothèses',
        'souhaite_protection_juridique' => 'Souhaite protection juridique',
        'souhaite_protection_juridique_conjoint' => 'Souhaite protection juridique conjoint',

        // Prévoyance
        'date_effet' => 'Date d\'effet',
        'cotisations' => 'Cotisations',
        'souhaite_couverture_invalidite' => 'Souhaite couverture invalidité',
        'revenu_a_garantir' => 'Revenu à garantir',
        'souhaite_couvrir_charges_professionnelles' => 'Souhaite couvrir charges pro',
        'montant_annuel_charges_professionnelles' => 'Montant annuel charges pro',
        'garantir_totalite_charges_professionnelles' => 'Garantir totalité charges pro',
        'montant_charges_professionnelles_a_garantir' => 'Montant charges pro à garantir',
        'duree_indemnisation_souhaitee' => 'Durée indemnisation souhaitée',
        'capital_deces_souhaite' => 'Capital décès souhaité',
        'garanties_obseques' => 'Garanties obsèques',
        'rente_enfants' => 'Rente enfants',
        'rente_conjoint' => 'Rente conjoint',
        'payeur' => 'Payeur',

        // Retraite
        'revenus_annuels_foyer' => 'Revenus annuels foyer',
        'impot_revenu' => 'Impôt sur le revenu',
        'nombre_parts_fiscales' => 'Nombre parts fiscales',
        'tmi' => 'TMI',
        'impot_paye_n_1' => 'Impôt payé N-1',
        'age_depart_retraite' => 'Âge départ retraite',
        'age_depart_retraite_conjoint' => 'Âge départ retraite conjoint',
        'pourcentage_revenu_a_maintenir' => '% revenu à maintenir',
        'bilan_retraite_disponible' => 'Bilan retraite disponible',
        'complementaire_retraite_mise_en_place' => 'Complémentaire retraite en place',
        'designation_etablissement' => 'Établissement',
        'cotisations_annuelles' => 'Cotisations annuelles',
        'titulaire' => 'Titulaire',

        // Épargne
        'epargne_disponible' => 'Épargne disponible',
        'montant_epargne_disponible' => 'Montant épargne disponible',
        'donation_realisee' => 'Donation réalisée',
        'donation_forme' => 'Forme donation',
        'donation_date' => 'Date donation',
        'donation_montant' => 'Montant donation',
        'donation_beneficiaires' => 'Bénéficiaires donation',
        'capacite_epargne_estimee' => 'Capacité épargne estimée',
        'actifs_financiers_pourcentage' => '% actifs financiers',
        'actifs_financiers_total' => 'Total actifs financiers',
        'actifs_financiers_details' => 'Détails actifs financiers',
        'actifs_immo_pourcentage' => '% actifs immobiliers',
        'actifs_immo_total' => 'Total actifs immobiliers',
        'actifs_immo_details' => 'Détails actifs immobiliers',
        'actifs_autres_pourcentage' => '% autres actifs',
        'actifs_autres_total' => 'Total autres actifs',
        'actifs_autres_details' => 'Détails autres actifs',
        'passifs_total_emprunts' => 'Total emprunts',
        'passifs_details' => 'Détails passifs',
        'charges_totales' => 'Charges totales',
        'charges_details' => 'Détails charges',
        'situation_financiere_revenus_charges' => 'Situation financière',

        // Revenus
        'nature' => 'Nature',
        'details' => 'Détails',
        'periodicite' => 'Périodicité',
        'montant' => 'Montant',

        // Actifs financiers
        'etablissement' => 'Établissement',
        'detenteur' => 'Détenteur',
        'date_ouverture_souscription' => 'Date ouverture/souscription',
        'valeur_actuelle' => 'Valeur actuelle',

        // Biens immobiliers
        'designation' => 'Désignation',
        'forme_propriete' => 'Forme de propriété',
        'valeur_actuelle_estimee' => 'Valeur actuelle estimée',
        'annee_acquisition' => 'Année d\'acquisition',
        'valeur_acquisition' => 'Valeur d\'acquisition',

        // Passifs
        'preteur' => 'Prêteur',
        'montant_remboursement' => 'Montant remboursement',
        'capital_restant_du' => 'Capital restant dû',
        'duree_restante' => 'Durée restante',

        // Autres épargnes
        'valeur' => 'Valeur',

        // Entreprise
        'raison_sociale' => 'Raison sociale',
        'forme_juridique' => 'Forme juridique',
        'siret' => 'SIRET',
        'siren' => 'SIREN',
        'capital_social' => 'Capital social',
        'date_creation' => 'Date de création',
        'activite' => 'Activité',
        'effectif' => 'Effectif',
        'chiffre_affaires' => 'Chiffre d\'affaires',
        'resultat_net' => 'Résultat net',

        // Questionnaire risque
        'profil_risque' => 'Profil de risque',
        'horizon_placement' => 'Horizon de placement',
        'objectif_investissement' => 'Objectif d\'investissement',
        'experience_financiere' => 'Expérience financière',
        'tolerance_perte' => 'Tolérance à la perte',
    ];

    /**
     * Champs à exclure du mapping (générés automatiquement)
     */
    private const EXCLUDED_FIELDS = [
        'id',
        'client_id',
        'team_id',
        'user_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'der_charge_clientele_id',
        'der_lieu_rdv',
        'der_date_rdv',
        'der_heure_rdv',
        'transcription_path',
        'consentement_audio',
        'besoins',
    ];

    /**
     * Récupère tous les champs mappables groupés par table
     */
    public function getAllMappableFields(): array
    {
        $result = [];

        foreach (self::TABLE_CONFIG as $tableName => $config) {
            $modelClass = $config['model'];
            $model = new $modelClass();
            $fillable = $model->getFillable();

            // Filtrer les champs exclus
            $fields = array_filter($fillable, fn($field) => !in_array($field, self::EXCLUDED_FIELDS));

            $tableFields = [];
            foreach ($fields as $field) {
                $tableFields[] = [
                    'field' => $field,
                    'label' => $this->getFieldLabel($field),
                    'full_key' => $this->getFullKey($tableName, $field, $config),
                ];
            }

            // Pour les tables multiples (hasMany), générer les champs indexés
            if ($config['multiple'] && isset($config['max_items'])) {
                $indexedFields = [];
                for ($i = 1; $i <= $config['max_items']; $i++) {
                    foreach ($tableFields as $fieldInfo) {
                        $prefix = str_replace('{n}', $i, $config['prefix']);
                        $indexedFields[] = [
                            'field' => $fieldInfo['field'],
                            'label' => $fieldInfo['label'] . " (#{$i})",
                            'full_key' => $prefix . $fieldInfo['field'],
                            'index' => $i,
                        ];
                    }
                }
                $tableFields = $indexedFields;
            }

            $result[$tableName] = [
                'label' => $config['label'],
                'relation' => $config['relation'],
                'multiple' => $config['multiple'],
                'fields' => $tableFields,
            ];
        }

        return $result;
    }

    /**
     * Récupère une liste plate de tous les champs pour un select
     */
    public function getFlatFieldsList(): array
    {
        $allFields = $this->getAllMappableFields();
        $flatList = [];

        foreach ($allFields as $tableName => $tableData) {
            $groupLabel = $tableData['label'];

            foreach ($tableData['fields'] as $fieldInfo) {
                $flatList[] = [
                    'value' => $fieldInfo['full_key'],
                    'label' => $fieldInfo['label'],
                    'group' => $groupLabel,
                    'table' => $tableName,
                    'field' => $fieldInfo['field'],
                    'index' => $fieldInfo['index'] ?? null,
                ];
            }
        }

        return $flatList;
    }

    /**
     * Récupère les champs groupés pour un select avec optgroup
     */
    public function getGroupedFieldsForSelect(): array
    {
        $allFields = $this->getAllMappableFields();
        $grouped = [];

        foreach ($allFields as $tableName => $tableData) {
            $options = [];
            foreach ($tableData['fields'] as $fieldInfo) {
                $options[] = [
                    'value' => $fieldInfo['full_key'],
                    'label' => $fieldInfo['label'],
                ];
            }

            $grouped[] = [
                'label' => $tableData['label'],
                'table' => $tableName,
                'options' => $options,
            ];
        }

        return $grouped;
    }

    /**
     * Récupère le label français d'un champ
     */
    private function getFieldLabel(string $field): string
    {
        if (isset(self::FIELD_LABELS[$field])) {
            return self::FIELD_LABELS[$field];
        }

        // Fallback: convertir snake_case en label lisible
        return ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Génère la clé complète pour un champ
     */
    private function getFullKey(string $tableName, string $field, array $config): string
    {
        if ($tableName === 'client') {
            return $field;
        }

        $prefix = $config['prefix'] ?? ($tableName . '_');

        // Pour les tables non-multiples, on enlève le placeholder {n}
        if (!$config['multiple']) {
            return $prefix . $field;
        }

        return $prefix . $field;
    }

    /**
     * Parse une clé de mapping pour retrouver table, champ et index
     */
    public function parseFieldKey(string $fullKey): ?array
    {
        // Client direct (pas de préfixe)
        $clientModel = new Client();
        if (in_array($fullKey, $clientModel->getFillable())) {
            return [
                'table' => 'client',
                'field' => $fullKey,
                'index' => null,
            ];
        }

        // Tables avec préfixe
        foreach (self::TABLE_CONFIG as $tableName => $config) {
            if ($tableName === 'client') continue;

            $prefix = $config['prefix'] ?? '';

            if ($config['multiple']) {
                // Pattern pour tables multiples: prefix{n}_field
                $basePrefix = str_replace('{n}', '', $prefix);
                if (preg_match('/^' . preg_quote($basePrefix, '/') . '(\d+)_(.+)$/', $fullKey, $matches)) {
                    return [
                        'table' => $tableName,
                        'field' => $matches[2],
                        'index' => (int)$matches[1],
                    ];
                }
            } else {
                // Pattern pour tables simples: prefix_field
                if (str_starts_with($fullKey, $prefix)) {
                    return [
                        'table' => $tableName,
                        'field' => substr($fullKey, strlen($prefix)),
                        'index' => null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Récupère la configuration d'une table
     */
    public function getTableConfig(string $tableName): ?array
    {
        return self::TABLE_CONFIG[$tableName] ?? null;
    }

    /**
     * Récupère toutes les configurations de tables
     */
    public function getAllTableConfigs(): array
    {
        return self::TABLE_CONFIG;
    }
}
