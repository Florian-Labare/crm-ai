<?php

/**
 * Script de vérification de la conformité des variables des templates
 * avec les colonnes de la base de données
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "🔍 VÉRIFICATION DE LA CONFORMITÉ DES VARIABLES DES TEMPLATES\n";
echo str_repeat('=', 80)."\n\n";

// =============================================================================
// ÉTAPE 1: Récupérer toutes les colonnes de toutes les tables
// =============================================================================

$tables = [
    'clients',
    'conjoints',
    'enfants',
    'bae_epargne',
    'bae_prevoyance',
    'bae_retraite',
    'sante_souhaits',
    'questionnaire_risques',
    'questionnaire_risque_financiers',
    'questionnaire_risque_connaissances',
];

$dbColumns = [];

foreach ($tables as $table) {
    try {
        $columns = Schema::getColumnListing($table);
        $dbColumns[$table] = $columns;
        echo "✅ Table '{$table}': ".count($columns)." colonnes\n";
    } catch (\Exception $e) {
        echo "❌ Erreur pour la table '{$table}': ".$e->getMessage()."\n";
    }
}

echo "\n".str_repeat('-', 80)."\n\n";

// =============================================================================
// ÉTAPE 2: Extraire toutes les variables des templates
// =============================================================================

$templatesDir = __DIR__.'/storage/app/templates';
$templates = glob($templatesDir.'/*.docx');

$allTemplateVariables = [];

foreach ($templates as $templatePath) {
    $templateName = basename($templatePath);
    echo "📄 Analyse du template: {$templateName}\n";

    try {
        $zip = new ZipArchive();
        if ($zip->open($templatePath) !== true) {
            echo "   ❌ Impossible d'ouvrir le fichier\n";

            continue;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xml) {
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
        $variables = array_filter($variables, fn ($v) => ! empty($v));

        $allTemplateVariables[$templateName] = $variables;

        echo '   Variables détectées: '.count($variables)."\n";

    } catch (\Exception $e) {
        echo '   ❌ Erreur: '.$e->getMessage()."\n";
    }
}

echo "\n".str_repeat('-', 80)."\n\n";

// =============================================================================
// ÉTAPE 3: Vérifier la conformité de chaque variable
// =============================================================================

echo "🔍 VÉRIFICATION DE LA CONFORMITÉ\n";
echo str_repeat('=', 80)."\n\n";

$totalVariables = 0;
$validVariables = 0;
$invalidVariables = 0;
$computedVariables = 0;
$issues = [];

foreach ($allTemplateVariables as $templateName => $variables) {
    echo "📄 Template: {$templateName}\n";
    echo str_repeat('-', 80)."\n";

    foreach ($variables as $variable) {
        $totalVariables++;

        // Variables spéciales (computed)
        if (in_array($variable, ['current_date', 'enfants.count'])) {
            echo "   🔵 {$variable} (computed) ✅\n";
            $computedVariables++;
            $validVariables++;

            continue;
        }

        // Parser la variable: {{table.colonne}} ou {{table[index].colonne}}
        if (preg_match('/^([a-z_]+)(?:\[(\d+)\])?\.([a-z_]+)$/i', $variable, $parts)) {
            $table = $parts[1];
            $index = $parts[2] ?? null;
            $column = $parts[3];

            // Vérifier si la table existe
            if (! isset($dbColumns[$table])) {
                echo "   ❌ {$variable} → Table '{$table}' introuvable\n";
                $invalidVariables++;
                $issues[] = [
                    'template' => $templateName,
                    'variable' => $variable,
                    'issue' => "Table '{$table}' n'existe pas",
                ];

                continue;
            }

            // Cas spécial: full_name (computed)
            if ($column === 'full_name' || $column === 'nom_complet') {
                echo "   🔵 {$variable} (computed) ✅\n";
                $computedVariables++;
                $validVariables++;

                continue;
            }

            // Vérifier si la colonne existe
            if (! in_array($column, $dbColumns[$table])) {
                echo "   ❌ {$variable} → Colonne '{$column}' introuvable dans '{$table}'\n";
                $invalidVariables++;
                $issues[] = [
                    'template' => $templateName,
                    'variable' => $variable,
                    'issue' => "Colonne '{$column}' n'existe pas dans la table '{$table}'",
                ];

                continue;
            }

            // Tout est OK
            echo "   ✅ {$variable}\n";
            $validVariables++;

        } else {
            // Format invalide
            echo "   ⚠️  {$variable} → Format invalide (attendu: table.colonne)\n";
            $invalidVariables++;
            $issues[] = [
                'template' => $templateName,
                'variable' => $variable,
                'issue' => 'Format invalide (attendu: table.colonne ou table[index].colonne)',
            ];
        }
    }

    echo "\n";
}

// =============================================================================
// ÉTAPE 4: Résumé
// =============================================================================

echo str_repeat('=', 80)."\n";
echo "📊 RÉSUMÉ\n";
echo str_repeat('=', 80)."\n\n";

echo "Total de variables analysées: {$totalVariables}\n";
echo "  ✅ Variables valides: {$validVariables}\n";
echo "  🔵 Variables computed: {$computedVariables}\n";
echo "  ❌ Variables invalides: {$invalidVariables}\n";

$successRate = $totalVariables > 0 ? round(($validVariables / $totalVariables) * 100, 2) : 0;
echo "\n📈 Taux de conformité: {$successRate}%\n";

if (! empty($issues)) {
    echo "\n".str_repeat('=', 80)."\n";
    echo "🚨 PROBLÈMES DÉTECTÉS\n";
    echo str_repeat('=', 80)."\n\n";

    foreach ($issues as $issue) {
        echo "❌ Template: {$issue['template']}\n";
        echo "   Variable: {{$issue['variable']}}\n";
        echo "   Problème: {$issue['issue']}\n";
        echo "\n";
    }
} else {
    echo "\n✅ Aucun problème détecté ! Tous les templates sont conformes.\n";
}

echo "\n".str_repeat('=', 80)."\n";

// =============================================================================
// ÉTAPE 5: Suggestions de colonnes manquantes
// =============================================================================

if ($invalidVariables > 0) {
    echo "\n💡 SUGGESTIONS POUR CORRIGER LES PROBLÈMES\n";
    echo str_repeat('=', 80)."\n\n";

    echo "1. Vérifier les noms de colonnes dans les templates\n";
    echo "2. Ajouter les colonnes manquantes dans les migrations\n";
    echo "3. Utiliser le format exact: {{table.colonne}}\n";
    echo "4. Les noms de tables doivent être au SINGULIER (convention Laravel Boost)\n";
    echo "\n";
}

echo "🏁 Vérification terminée.\n";
