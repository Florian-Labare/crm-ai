<?php

/**
 * Génère automatiquement le mapping pour les 57 variables non mappées
 *
 * Analyse intelligente basée sur:
 * - Les tables existantes (clients, conjoints, bae_*, sante_souhait, etc.)
 * - Les conventions de nommage
 * - Le contexte métier (assurance)
 */

require __DIR__ . '/vendor/autoload.php';

// Charger le mapping automatique
$mappingFile = __DIR__ . '/document-variables-mapping.json';
$mappingData = json_decode(file_get_contents($mappingFile), true);
$unmappedVariables = $mappingData['unmapped'];

echo "🔍 Analyse de " . count($unmappedVariables) . " variables non mappées\n";
echo str_repeat("=", 80) . "\n\n";

/**
 * Définitions des mappings pour les 57 variables
 * Structure: variable => [source, field, format, needs_migration, comment]
 */
$intelligentMappings = [
    // === SANTÉ (12 variables) - Table: sante_souhait ===
    'AnalyseImagerie' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_imagerie',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Imagerie médicale (IRM, scanner, radio)',
    ],
    'AuxiliairesMédicaux' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_auxiliaires_medicaux',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Infirmiers, kinés, orthophonistes',
    ],
    'Dentaire' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_dentaire',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Soins dentaires',
    ],
    'Hospitalisation' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_hospitalisation',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Couverture hospitalisation',
    ],
    'MédecinGénéralisteetspécialiste' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_medecins',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Consultations médecins',
    ],
    'autresprotheses' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_autres_protheses',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Prothèses diverses (hors auditives)',
    ],
    'curesthermales' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_cures_thermales',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Cures thermales',
    ],
    'medecinedouce' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_medecine_douce',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Ostéopathie, acupuncture, etc.',
    ],
    'optiquelentilles' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_optique',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Lunettes et lentilles',
    ],
    'protheseauditive' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_prothese_auditive',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Appareils auditifs',
    ],
    'protectionjuridique' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_protection_juridique',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Protection juridique',
    ],
    'protectionjuridiqueconjoint' => [
        'source' => 'sante_souhait',
        'field' => 'souhaite_protection_juridique_conjoint',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Protection juridique conjoint',
    ],

    // === FINANCIERS (4 variables) ===
    'Impôtsurlerevenupayéenn' => [
        'source' => 'bae_retraite',
        'field' => 'impot_paye_n_1',
        'format' => 'currency',
        'needs_migration' => false,
        'comment' => 'Alias de impotrevenunmoins1 (existe déjà)',
    ],
    'Montantépargnedisponible' => [
        'source' => 'bae_epargne',
        'field' => 'montant_epargne_disponible',
        'format' => 'currency',
        'needs_migration' => true,
        'comment' => 'Épargne liquide disponible',
    ],
    'Totalemprunts' => [
        'source' => 'bae_epargne',
        'field' => 'total_emprunts',
        'format' => 'currency',
        'needs_migration' => true,
        'comment' => 'Total des emprunts en cours',
    ],
    'Leclientdispose-t-ilduneépargnedisponible(liquide)' => [
        'source' => 'computed',
        'field' => null,
        'format' => 'boolean',
        'needs_migration' => false,
        'comment' => 'Calculé: montant_epargne_disponible > 0',
        'computed' => function ($client) {
            if (!$client->baeEpargne || !$client->baeEpargne->montant_epargne_disponible) {
                return 'Non';
            }
            return $client->baeEpargne->montant_epargne_disponible > 0 ? 'Oui' : 'Non';
        },
    ],

    // === PROFIL DE RISQUE (3 variables) ===
    'Latoléranceaurisqueduclientest' => [
        'source' => 'questionnaire_risque',
        'field' => 'tolerance_risque',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Description tolérance au risque',
    ],
    'Pourcentagemaxperte' => [
        'source' => 'questionnaire_risque',
        'field' => 'pourcentage_max_perte',
        'format' => 'number',
        'needs_migration' => true,
        'comment' => 'Perte maximale acceptable (%)',
    ],
    'Votrehorizond\'investissement' => [
        'source' => 'questionnaire_financier',
        'field' => 'horizon_investissement',
        'format' => 'enum',
        'needs_migration' => false,
        'comment' => 'Existe déjà dans horizoninvestobjectiff',
    ],

    // === PROFESSIONNELS (11 variables) ===
    'Travailleurindépendant' => [
        'source' => 'client',
        'field' => 'travailleur_independant',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Statut indépendant',
    ],
    'siindependant' => [
        'source' => 'client',
        'field' => 'travailleur_independant',
        'format' => 'boolean',
        'needs_migration' => false,
        'comment' => 'Alias de Travailleurindépendant',
    ],
    'siindependantconjoint' => [
        'source' => 'conjoint',
        'field' => 'travailleur_independant',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Statut indépendant conjoint',
    ],
    'deplacementpro' => [
        'source' => 'bae_prevoyance',
        'field' => 'deplacements_professionnels',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Nature des déplacements pro',
    ],
    'deplacementproconjoint' => [
        'source' => 'bae_prevoyance',
        'field' => 'deplacements_professionnels_conjoint',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Déplacements pro conjoint',
    ],
    'dureeindemnisationfraispro' => [
        'source' => 'bae_prevoyance',
        'field' => 'duree_indemnisation_frais_pro',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Durée souhaitée indemnisation',
    ],
    'montantannuelprocouvert' => [
        'source' => 'bae_prevoyance',
        'field' => 'montant_annuel_frais_pro',
        'format' => 'currency',
        'needs_migration' => true,
        'comment' => 'Montant annuel frais pro à garantir',
    ],
    'professionactuelleouancienne' => [
        'source' => 'client',
        'field' => 'profession',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de profession (existe)',
    ],
    'professionactuelleouancienneconjoint' => [
        'source' => 'conjoint',
        'field' => 'profession',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de professionconjointnn (existe)',
    ],
    'situationpro' => [
        'source' => 'client',
        'field' => 'situation_professionnelle',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Situation professionnelle détaillée',
    ],
    'situationproconjoint' => [
        'source' => 'conjoint',
        'field' => 'situation_professionnelle',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Situation pro conjoint',
    ],
    'statutsiactivite' => [
        'source' => 'client',
        'field' => 'statut',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de Statut (existe)',
    ],
    'statutsiactiviteconjoint' => [
        'source' => 'conjoint',
        'field' => 'statut',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Statut professionnel conjoint',
    ],

    // === PRÉVOYANCE (8 variables) ===
    'couvertinvalidite' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_couverture_invalidite',
        'format' => 'boolean',
        'needs_migration' => false,
        'comment' => 'Alias de invaliditecouvert (existe)',
    ],
    'couvrirchargespro' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_couvrir_charges_professionnelles',
        'format' => 'boolean',
        'needs_migration' => false,
        'comment' => 'Alias de chargesprocouvert (existe)',
    ],
    'dénominationcontratprev' => [
        'source' => 'bae_prevoyance',
        'field' => 'denomination_contrat',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Nom du contrat prévoyance actuel',
    ],
    'montantprevgarantie' => [
        'source' => 'bae_prevoyance',
        'field' => 'montant_garanti',
        'format' => 'currency',
        'needs_migration' => true,
        'comment' => 'Montant de garantie souhaité',
    ],
    'procheprotecdeces' => [
        'source' => 'bae_prevoyance',
        'field' => 'capital_deces_souhaite',
        'format' => 'currency',
        'needs_migration' => false,
        'comment' => 'Alias de casdecesproche (existe)',
    ],
    'siouicharges' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_couvrir_charges_professionnelles',
        'format' => 'boolean',
        'needs_migration' => false,
        'comment' => 'Duplicate de couvrirchargespro',
    ],
    'siouimandataire' => [
        'source' => 'client',
        'field' => 'mandataire_social',
        'format' => 'boolean',
        'needs_migration' => false,
        'comment' => 'Alias de Mandatairesocial (existe)',
    ],
    'siouioutillage' => [
        'source' => 'bae_prevoyance',
        'field' => 'souhaite_garantie_outillage',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Garantie outillage professionnel',
    ],
    '​​montantchargecouverte' => [
        'source' => 'bae_prevoyance',
        'field' => 'montant_charges_professionnelles_a_garantir',
        'format' => 'currency',
        'needs_migration' => false,
        'comment' => 'Alias de chargesprofessionnelles (existe)',
    ],

    // === ACTIVITÉ SPORTIVE (4 variables) ===
    'niveauactivite' => [
        'source' => 'client',
        'field' => 'niveau_activite_sportive',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Occasionnel/Régulier/Intensif',
    ],
    'niveauactivitesportiveconjoint' => [
        'source' => 'conjoint',
        'field' => 'niveau_activite_sportive',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Niveau activité sportive conjoint',
    ],
    'typeactivitesportiveconjoint' => [
        'source' => 'conjoint',
        'field' => 'details_activites_sportives',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Type d\'activité sportive conjoint',
    ],
    'nbkmparan' => [
        'source' => 'client',
        'field' => 'km_parcourus_annuels',
        'format' => 'number',
        'needs_migration' => true,
        'comment' => 'Kilomètres parcourus par an (véhicule)',
    ],

    // === RETRAITE (2 variables) ===
    'Agedudépartàlaretraite' => [
        'source' => 'bae_retraite',
        'field' => 'age_depart_retraite',
        'format' => 'number',
        'needs_migration' => false,
        'comment' => 'Alias de ageretraitedepart (existe)',
    ],
    'dateretraiteevenement' => [
        'source' => 'bae_retraite',
        'field' => 'date_evenement_retraite',
        'format' => 'date',
        'needs_migration' => true,
        'comment' => 'Date prévue départ à la retraite',
    ],

    // === GÉNÉRAUX (9 variables) ===
    'Résidencefiscale' => [
        'source' => 'client',
        'field' => 'pays_residence_fiscale',
        'format' => 'text',
        'needs_migration' => true,
        'comment' => 'Pays de résidence fiscale',
    ],
    'residencefiscale' => [
        'source' => 'client',
        'field' => 'pays_residence_fiscale',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de Résidencefiscale',
    ],
    'Téléphone' => [
        'source' => 'client',
        'field' => 'telephone',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de numerotel (existe)',
    ],
    'adressepersop' => [
        'source' => 'client',
        'field' => 'adresse',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de adresse (existe)',
    ],
    'etatcivile' => [
        'source' => 'client',
        'field' => 'situation_matrimoniale',
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Alias de situationmatrimoniale (existe)',
    ],
    'genre' => [
        'source' => 'client',
        'field' => 'genre',
        'format' => 'enum',
        'needs_migration' => true,
        'comment' => 'Sexe: M/F',
    ],
    'SOCOGEAvousindique' => [
        'source' => 'computed',
        'field' => null,
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Texte statique commercial',
        'computed' => fn($client) => 'SOCOGEA vous indique',
    ],
    'SOCOGEAvousindiqueque' => [
        'source' => 'computed',
        'field' => null,
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Texte statique commercial',
        'computed' => fn($client) => 'SOCOGEA vous indique que',
    ],
    'Leprésentrapportrépond' => [
        'source' => 'computed',
        'field' => null,
        'format' => 'text',
        'needs_migration' => false,
        'comment' => 'Texte statique rapport',
        'computed' => fn($client) => 'Le présent rapport répond',
    ],

    // === SITUATION CONJOINT (1 variable) ===
    'situationconjointchomage' => [
        'source' => 'conjoint',
        'field' => 'situation_chomage',
        'format' => 'boolean',
        'needs_migration' => true,
        'comment' => 'Conjoint au chômage',
    ],
];

// Catégoriser les variables
$categories = [
    'santé' => [],
    'financiers' => [],
    'profil_risque' => [],
    'professionnels' => [],
    'prévoyance' => [],
    'activité_sportive' => [],
    'retraite' => [],
    'généraux' => [],
];

// Stats
$needsMigration = [];
$existingFields = [];
$computedFields = [];

// Trier les mappings par catégorie
foreach ($intelligentMappings as $variable => $mapping) {
    if ($mapping['needs_migration']) {
        $needsMigration[] = $variable;
    } else {
        $existingFields[] = $variable;
    }

    if ($mapping['source'] === 'computed') {
        $computedFields[] = $variable;
    }
}

echo "📊 STATISTIQUES\n";
echo str_repeat("-", 80) . "\n";
echo "Variables mappées: " . count($intelligentMappings) . "/" . count($unmappedVariables) . "\n";
echo "Champs existants (alias): " . count($existingFields) . "\n";
echo "Nouveaux champs (migration requise): " . count($needsMigration) . "\n";
echo "Champs calculés: " . count($computedFields) . "\n\n";

// Afficher les mappings par catégorie
echo "📋 MAPPINGS DÉTAILLÉS\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($intelligentMappings as $variable => $mapping) {
    $status = $mapping['needs_migration'] ? '🆕' : '✅';
    $sourceDisplay = $mapping['source'];
    if ($mapping['field']) {
        $sourceDisplay .= '.' . $mapping['field'];
    }

    echo str_pad($variable, 45) . " {$status} " . str_pad($sourceDisplay, 30) . "\n";
    echo "    → " . $mapping['comment'] . "\n\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "💾 GÉNÉRATION DU CODE PHP\n";
echo str_repeat("=", 80) . "\n\n";

// Générer le code PHP pour config/document_mapping.php
$phpCode = "\n    // === VARIABLES AJOUTÉES AUTOMATIQUEMENT - MIGRATION COMPLÈTE ===\n\n";

// Grouper par source
$groupedMappings = [];
foreach ($intelligentMappings as $variable => $mapping) {
    $source = $mapping['source'];
    if (!isset($groupedMappings[$source])) {
        $groupedMappings[$source] = [];
    }
    $groupedMappings[$source][$variable] = $mapping;
}

// Générer le code par groupe
$sourceLabels = [
    'sante_souhait' => 'SANTÉ - SOUHAITS CLIENT',
    'bae_epargne' => 'BAE ÉPARGNE - COMPLÉMENTS',
    'bae_retraite' => 'BAE RETRAITE - COMPLÉMENTS',
    'bae_prevoyance' => 'BAE PRÉVOYANCE - COMPLÉMENTS',
    'questionnaire_risque' => 'QUESTIONNAIRE RISQUE',
    'questionnaire_financier' => 'QUESTIONNAIRE FINANCIER - COMPLÉMENTS',
    'client' => 'CLIENT - COMPLÉMENTS',
    'conjoint' => 'CONJOINT - COMPLÉMENTS',
    'computed' => 'CHAMPS CALCULÉS',
];

foreach ($groupedMappings as $source => $vars) {
    $label = $sourceLabels[$source] ?? strtoupper($source);
    $phpCode .= "    // === {$label} ===\n";

    foreach ($vars as $variable => $mapping) {
        $phpCode .= "    '{$variable}' => ";

        if ($mapping['source'] === 'computed' && isset($mapping['computed'])) {
            // Champ calculé avec closure
            $funcCode = "[\n";
            $funcCode .= "        'source' => 'computed',\n";

            // Générer le code de la fonction
            if (is_string($mapping['computed'])) {
                $funcCode .= "        'computed' => fn(\$client) => " . var_export($mapping['computed'], true) . ",\n";
            } else {
                // Cas spéciaux
                if ($variable === 'Leclientdispose-t-ilduneépargnedisponible(liquide)') {
                    $funcCode .= "        'computed' => function (\$client) {\n";
                    $funcCode .= "            if (!\$client->baeEpargne || !\$client->baeEpargne->montant_epargne_disponible) {\n";
                    $funcCode .= "                return 'Non';\n";
                    $funcCode .= "            }\n";
                    $funcCode .= "            return \$client->baeEpargne->montant_epargne_disponible > 0 ? 'Oui' : 'Non';\n";
                    $funcCode .= "        },\n";
                } else {
                    // Textes statiques
                    $staticTexts = [
                        'SOCOGEAvousindique' => 'SOCOGEA vous indique',
                        'SOCOGEAvousindiqueque' => 'SOCOGEA vous indique que',
                        'Leprésentrapportrépond' => 'Le présent rapport répond',
                    ];
                    if (isset($staticTexts[$variable])) {
                        $funcCode .= "        'computed' => fn(\$client) => '" . $staticTexts[$variable] . "',\n";
                    }
                }
            }

            $funcCode .= "    ],";
            $phpCode .= $funcCode;
        } else {
            // Champ standard
            $config = ['source' => $mapping['source']];
            if ($mapping['field']) {
                $config['field'] = $mapping['field'];
            }
            if ($mapping['format'] && $mapping['format'] !== 'text') {
                $config['format'] = $mapping['format'];
            }

            $phpCode .= var_export($config, true) . ',';
        }

        $phpCode .= " // " . $mapping['comment'] . "\n";
    }

    $phpCode .= "\n";
}

echo $phpCode;

// Sauvegarder dans un fichier
$outputFile = __DIR__ . '/mapping-code-to-add.php';
file_put_contents($outputFile, "<?php\n\n/**\n * Code à ajouter dans config/document_mapping.php\n */\n\nreturn [\n" . $phpCode . "];\n");

echo "✅ Code PHP sauvegardé dans: mapping-code-to-add.php\n\n";

// Générer les migrations nécessaires
echo str_repeat("=", 80) . "\n";
echo "🗄️  MIGRATIONS À CRÉER\n";
echo str_repeat("=", 80) . "\n\n";

$migrationsByTable = [
    'clients' => [],
    'conjoints' => [],
    'bae_prevoyances' => [],
    'bae_retraites' => [],
    'bae_epargnes' => [],
    'sante_souhaits' => [],
    'questionnaire_risques' => [],
];

foreach ($intelligentMappings as $variable => $mapping) {
    if (!$mapping['needs_migration'] || !$mapping['field']) {
        continue;
    }

    $table = match($mapping['source']) {
        'client' => 'clients',
        'conjoint' => 'conjoints',
        'bae_prevoyance' => 'bae_prevoyances',
        'bae_retraite' => 'bae_retraites',
        'bae_epargne' => 'bae_epargnes',
        'sante_souhait' => 'sante_souhaits',
        'questionnaire_risque' => 'questionnaire_risques',
        default => null,
    };

    if ($table && !in_array($mapping['field'], $migrationsByTable[$table])) {
        $migrationsByTable[$table][] = [
            'field' => $mapping['field'],
            'type' => match($mapping['format']) {
                'boolean' => 'boolean',
                'currency' => 'decimal',
                'number' => 'integer',
                'date' => 'date',
                'enum' => 'string',
                default => 'text',
            },
            'comment' => $mapping['comment'],
        ];
    }
}

foreach ($migrationsByTable as $table => $fields) {
    if (empty($fields)) {
        continue;
    }

    echo "📦 Table: {$table} (" . count($fields) . " nouveaux champs)\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($fields as $fieldData) {
        $field = $fieldData['field'];
        $type = $fieldData['type'];
        $comment = $fieldData['comment'];

        $migration = match($type) {
            'boolean' => "\$table->boolean('{$field}')->nullable();",
            'decimal' => "\$table->decimal('{$field}', 12, 2)->nullable();",
            'integer' => "\$table->integer('{$field}')->nullable();",
            'date' => "\$table->date('{$field}')->nullable();",
            default => "\$table->text('{$field}')->nullable();",
        };

        echo "    {$migration} // {$comment}\n";
    }

    echo "\n";
}

echo "✅ Analyse terminée !\n\n";
echo "📋 PROCHAINES ÉTAPES:\n";
echo "   1. Vérifier le code généré dans: mapping-code-to-add.php\n";
echo "   2. Créer les migrations pour les nouveaux champs\n";
echo "   3. Ajouter le code dans config/document_mapping.php\n";
echo "   4. Exécuter les migrations\n";
echo "   5. Nettoyer et renommer les variables dans les templates\n";
