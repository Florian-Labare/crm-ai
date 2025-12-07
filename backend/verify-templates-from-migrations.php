<?php

/**
 * Vérification des variables des templates contre les colonnes définies dans les migrations
 * (sans connexion BDD)
 */

require __DIR__ . '/vendor/autoload.php';

echo "🔍 VÉRIFICATION DE LA CONFORMITÉ DES VARIABLES DES TEMPLATES\n";
echo str_repeat("=", 80) . "\n\n";

// =============================================================================
// ÉTAPE 1: Définir manuellement les colonnes de chaque table (d'après les migrations)
// =============================================================================

$dbColumns = [
    'clients' => [
        'id', 'team_id', 'nom', 'prenom', 'nom_complet', 'civilite', 'email', 'telephone',
        'date_naissance', 'lieu_naissance', 'adresse', 'code_postal', 'ville',
        'nom_jeune_fille', 'nationalite', 'residence_fiscale', 'situation_matrimoniale',
        'date_situation_matrimoniale', 'situation_actuelle', 'date_evenement_professionnel',
        'profession', 'statut', 'chef_entreprise', 'travailleur_independant', 'mandataire_social',
        'fumeur', 'activites_sportives', 'details_activites_sportives', 'niveau_activites_sportives',
        'risques_professionnels', 'details_risques_professionnels', 'km_parcourus_annuels',
        'besoins', 'nombre_enfants', 'transcription_path', 'consentement_audio',
        'charge_clientele', 'user_id', 'created_at', 'updated_at', 'deleted_at',
    ],
    'conjoints' => [
        'id', 'client_id', 'nom', 'nom_jeune_fille', 'prenom', 'date_naissance', 'lieu_naissance',
        'nationalite', 'profession', 'situation_professionnelle', 'situation_chomage', 'statut',
        'chef_entreprise', 'travailleur_independant', 'situation_actuelle_statut',
        'niveau_activite_sportive', 'details_activites_sportives', 'date_evenement_professionnel',
        'risques_professionnels', 'details_risques_professionnels', 'telephone', 'adresse',
        'code_postal', 'ville', 'fumeur', 'km_parcourus_annuels', 'created_at', 'updated_at',
    ],
    'enfants' => [
        'id', 'team_id', 'client_id', 'nom', 'prenom', 'date_naissance',
        'fiscalement_a_charge', 'garde_alternee', 'created_at', 'updated_at',
    ],
    'bae_epargne' => [
        'id', 'team_id', 'client_id', 'montant_epargne_disponible', 'epargne_disponible',
        'capacite_epargne_estimee', 'donation_forme', 'donation_date', 'donation_montant',
        'donation_beneficiaires', 'passifs_total_emprunts', 'charges_totales',
        'situation_financiere_revenus_charges', 'actifs_immo_total', 'actifs_financiers_total',
        'actifs_financiers_details', 'created_at', 'updated_at',
    ],
    'bae_prevoyance' => [
        'id', 'team_id', 'client_id', 'contrat_en_place', 'duree_indemnisation_souhaitee',
        'souhaite_couverture_invalidite', 'deplacements_professionnels',
        'deplacements_professionnels_conjoint', 'capital_deces_souhaite', 'date_effet',
        'payeur', 'montant_annuel_charges_professionnelles', 'souhaite_couvrir_charges_professionnelles',
        'montant_charges_professionnelles_a_garantir', 'revenu_a_garantir',
        'created_at', 'updated_at',
    ],
    'bae_retraite' => [
        'id', 'client_id', 'revenus_annuels', 'revenus_annuels_foyer', 'impot_revenu',
        'nombre_parts_fiscales', 'tmi', 'impot_paye_n_1', 'age_depart_retraite',
        'date_evenement_retraite', 'age_depart_retraite_conjoint', 'pourcentage_revenu_a_maintenir',
        'contrat_en_place', 'bilan_retraite_disponible', 'complementaire_retraite_mise_en_place',
        'designation_etablissement', 'cotisations_annuelles', 'titulaire', 'created_at', 'updated_at',
    ],
    'sante_souhaits' => [
        'id', 'team_id', 'client_id', 'contrat_en_place', 'budget_mensuel_maximum',
        'niveau_hospitalisation', 'niveau_chambre_particuliere', 'niveau_medecin_generaliste',
        'niveau_analyses_imagerie', 'niveau_auxiliaires_medicaux', 'niveau_pharmacie',
        'niveau_dentaire', 'niveau_optique', 'niveau_protheses_auditives',
        'souhaite_protection_juridique', 'souhaite_protection_juridique_conjoint',
        'created_at', 'updated_at',
    ],
    'questionnaire_risques' => [
        'id', 'team_id', 'client_id', 'score_global', 'profil_calcule', 'recommandation',
        'created_at', 'updated_at',
    ],
    'questionnaire_risque_financiers' => [
        'id', 'questionnaire_risque_id', 'temps_attente_recuperation_valeur', 'niveau_perte_inquietude',
        'reaction_baisse_25', 'attitude_placements', 'allocation_epargne', 'objectif_placement',
        'placements_inquietude', 'epargne_precaution', 'reaction_moins_value', 'impact_baisse_train_vie',
        'perte_supportable', 'objectif_global', 'objectifs_rapport', 'horizon_investissement',
        'tolerance_risque', 'niveau_connaissance_globale', 'pourcentage_perte_max',
        'created_at', 'updated_at',
    ],
    'questionnaire_risque_connaissances' => [
        'id', 'team_id', 'questionnaire_risque_id', 'connaissance_actions', 'connaissance_obligations',
        'connaissance_opcvm', 'connaissance_produits_structures', 'connaissance_produits_derives',
        'connaissance_immobilier', 'connaissance_matieres_premieres', 'connaissance_private_equity',
        'connaissance_forex', 'connaissance_cryptomonnaies', 'experience_actions',
        'experience_obligations', 'experience_opcvm', 'experience_produits_structures',
        'experience_produits_derives', 'experience_immobilier', 'experience_matieres_premieres',
        'experience_private_equity', 'experience_forex', 'experience_cryptomonnaies',
        'created_at', 'updated_at',
    ],
    'client_revenus' => [
        'id', 'client_id', 'nature', 'periodicite', 'montant', 'created_at', 'updated_at',
    ],
    'client_passifs' => [
        'id', 'client_id', 'nature', 'preteur', 'periodicite', 'montant_remboursement',
        'capital_restant_du', 'duree_restante', 'created_at', 'updated_at',
    ],
    'client_actifs_financiers' => [
        'id', 'client_id', 'nature', 'etablissement', 'detenteur', 'date_ouverture_souscription',
        'valeur_actuelle', 'created_at', 'updated_at',
    ],
    'client_biens_immobiliers' => [
        'id', 'client_id', 'designation', 'detenteur', 'forme_propriete', 'valeur_actuelle_estimee',
        'annee_acquisition', 'valeur_acquisition', 'created_at', 'updated_at',
    ],
    'client_autres_epargnes' => [
        'id', 'client_id', 'designation', 'detenteur', 'valeur', 'created_at', 'updated_at',
    ],
];

echo "✅ Schéma de base de données chargé\n";
foreach ($dbColumns as $table => $columns) {
    echo "   - {$table}: " . count($columns) . " colonnes\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// =============================================================================
// ÉTAPE 2: Extraire toutes les variables des templates
// =============================================================================

$templatesDir = __DIR__ . '/storage/app/templates';
$templates = glob($templatesDir . '/*.docx');

$allTemplateVariables = [];

foreach ($templates as $templatePath) {
    $templateName = basename($templatePath);

    // Ignorer les backups
    if (str_contains($templateName, '_backup_')) {
        continue;
    }

    echo "📄 Analyse du template: {$templateName}\n";

    try {
        $zip = new ZipArchive();
        if ($zip->open($templatePath) !== TRUE) {
            echo "   ❌ Impossible d'ouvrir le fichier\n";
            continue;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            echo "   ❌ Impossible de lire document.xml\n";
            continue;
        }

        // Extraire toutes les variables {{xxx}}
        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
        $fullText = implode('', $matches[1]);
        $fullText = html_entity_decode($fullText, ENT_XML1);

        preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varMatches);
        $variables = array_unique($varMatches[1]);
        $variables = array_map('trim', $variables);
        $variables = array_filter($variables, fn($v) => !empty($v));

        $allTemplateVariables[$templateName] = $variables;

        echo "   Variables détectées: " . count($variables) . "\n";

    } catch (\Exception $e) {
        echo "   ❌ Erreur: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// =============================================================================
// ÉTAPE 3: Vérifier la conformité de chaque variable
// =============================================================================

echo "🔍 VÉRIFICATION DE LA CONFORMITÉ\n";
echo str_repeat("=", 80) . "\n\n";

$totalVariables = 0;
$validVariables = 0;
$invalidVariables = 0;
$computedVariables = 0;
$issues = [];

foreach ($allTemplateVariables as $templateName => $variables) {
    echo "📄 Template: {$templateName}\n";
    echo str_repeat("-", 80) . "\n";

    $templateIssues = 0;

    foreach ($variables as $variable) {
        $totalVariables++;

        // Variables spéciales (computed)
        if (in_array($variable, ['current_date', 'enfants.count'])) {
            // echo "   🔵 {$variable} (computed) ✅\n";
            $computedVariables++;
            $validVariables++;
            continue;
        }

        // Parser la variable: {{table.colonne}} ou {{table[index].colonne}}
        if (preg_match('/^([a-z_]+)(?:\[(\d+)\])?\.([a-z0-9_]+)$/i', $variable, $parts)) {
            $table = $parts[1];
            $index = $parts[2] ?? null;
            $column = $parts[3];

            // Vérifier si la table existe
            if (!isset($dbColumns[$table])) {
                echo "   ❌ {$variable} → Table '{$table}' introuvable\n";
                $invalidVariables++;
                $templateIssues++;
                $issues[] = [
                    'template' => $templateName,
                    'variable' => $variable,
                    'issue' => "Table '{$table}' n'existe pas",
                    'type' => 'table_not_found',
                ];
                continue;
            }

            // Cas spécial: full_name (computed)
            if ($column === 'full_name' || $column === 'nom_complet') {
                // echo "   🔵 {$variable} (computed) ✅\n";
                $computedVariables++;
                $validVariables++;
                continue;
            }

            // Vérifier si la colonne existe
            if (!in_array($column, $dbColumns[$table])) {
                echo "   ❌ {$variable} → Colonne '{$column}' introuvable dans '{$table}'\n";
                $invalidVariables++;
                $templateIssues++;
                $issues[] = [
                    'template' => $templateName,
                    'variable' => $variable,
                    'issue' => "Colonne '{$column}' n'existe pas dans la table '{$table}'",
                    'type' => 'column_not_found',
                    'table' => $table,
                    'column' => $column,
                ];
                continue;
            }

            // Tout est OK
            // echo "   ✅ {$variable}\n";
            $validVariables++;

        } else {
            // Format invalide
            echo "   ⚠️  {$variable} → Format invalide (attendu: table.colonne)\n";
            $invalidVariables++;
            $templateIssues++;
            $issues[] = [
                'template' => $templateName,
                'variable' => $variable,
                'issue' => "Format invalide (attendu: table.colonne ou table[index].colonne)",
                'type' => 'invalid_format',
            ];
        }
    }

    if ($templateIssues === 0) {
        echo "   ✅ Toutes les variables sont valides !\n";
    } else {
        echo "   ⚠️  {$templateIssues} problème(s) détecté(s)\n";
    }

    echo "\n";
}

// =============================================================================
// ÉTAPE 4: Résumé
// =============================================================================

echo str_repeat("=", 80) . "\n";
echo "📊 RÉSUMÉ\n";
echo str_repeat("=", 80) . "\n\n";

echo "Total de variables analysées: {$totalVariables}\n";
echo "  ✅ Variables valides: {$validVariables}\n";
echo "  🔵 Variables computed: {$computedVariables}\n";
echo "  ❌ Variables invalides: {$invalidVariables}\n";

$successRate = $totalVariables > 0 ? round(($validVariables / $totalVariables) * 100, 2) : 0;
echo "\n📈 Taux de conformité: {$successRate}%\n";

// Regrouper les problèmes par type
$issuesByType = [];
foreach ($issues as $issue) {
    $type = $issue['type'];
    if (!isset($issuesByType[$type])) {
        $issuesByType[$type] = [];
    }
    $issuesByType[$type][] = $issue;
}

if (!empty($issues)) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "🚨 PROBLÈMES DÉTECTÉS ({$invalidVariables} au total)\n";
    echo str_repeat("=", 80) . "\n\n";

    // Colonnes manquantes par table
    $missingColumns = [];
    foreach ($issues as $issue) {
        if ($issue['type'] === 'column_not_found') {
            $table = $issue['table'];
            $column = $issue['column'];
            if (!isset($missingColumns[$table])) {
                $missingColumns[$table] = [];
            }
            if (!in_array($column, $missingColumns[$table])) {
                $missingColumns[$table][] = $column;
            }
        }
    }

    if (!empty($missingColumns)) {
        echo "📋 Colonnes manquantes par table:\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($missingColumns as $table => $columns) {
            echo "\n❌ Table '{$table}' - " . count($columns) . " colonne(s) manquante(s):\n";
            foreach ($columns as $column) {
                echo "   - {$column}\n";
            }
        }
        echo "\n";
    }

    // Variables au format invalide
    if (isset($issuesByType['invalid_format'])) {
        echo "\n⚠️  Variables au format invalide (" . count($issuesByType['invalid_format']) . "):\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($issuesByType['invalid_format'] as $issue) {
            echo "   - {{$issue['variable']}} dans {$issue['template']}\n";
        }
    }

} else {
    echo "\n✅ Aucun problème détecté ! Tous les templates sont 100% conformes.\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "🏁 Vérification terminée.\n";
