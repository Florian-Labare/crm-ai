<?php

/**
 * Script pour analyser tous les documents contractuels et extraire leurs variables
 */

require __DIR__ . '/vendor/autoload.php';

$sourceDir = '/Users/florian/Documents/projet-courtier/DOCUMENTS CONTRACTUELS/';

if (!is_dir($sourceDir)) {
    die("❌ Dossier non trouvé: {$sourceDir}\n");
}

$files = glob($sourceDir . '*.docx');

echo "📁 Analyse de " . count($files) . " documents contractuels\n";
echo "=======================================================\n\n";

$allVariables = [];
$documentVariables = [];

foreach ($files as $filePath) {
    $fileName = basename($filePath);

    echo "📄 {$fileName}\n";

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== TRUE) {
        echo "   ❌ Impossible d'ouvrir\n\n";
        continue;
    }

    $xml = $zip->getFromName('word/document.xml');

    // Extraire tout le texte
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
    $fullText = implode('', $matches[1]);
    $fullText = html_entity_decode($fullText, ENT_XML1);

    // Extraire les variables
    preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varMatches);
    $variables = array_unique($varMatches[1]);

    // Nettoyer les variables (supprimer espaces, etc.)
    $variables = array_map('trim', $variables);
    $variables = array_filter($variables, fn($v) => !empty($v));

    sort($variables);

    echo "   Variables trouvées: " . count($variables) . "\n";

    $documentVariables[$fileName] = $variables;
    $allVariables = array_merge($allVariables, $variables);

    $zip->close();

    // Afficher les variables
    if (count($variables) > 0) {
        foreach ($variables as $var) {
            echo "      - {$var}\n";
        }
    }

    echo "\n";
}

// Dédupliquer toutes les variables
$allVariables = array_unique($allVariables);
sort($allVariables);

echo "=======================================================\n";
echo "📊 RÉSUMÉ GLOBAL\n";
echo "=======================================================\n\n";
echo "Total de variables uniques: " . count($allVariables) . "\n\n";

echo "Liste complète des variables:\n";
foreach ($allVariables as $var) {
    echo "   - {$var}\n";
}

// Sauvegarder dans un fichier JSON
$outputFile = __DIR__ . '/document-variables-analysis.json';
file_put_contents($outputFile, json_encode([
    'summary' => [
        'total_documents' => count($files),
        'total_unique_variables' => count($allVariables),
    ],
    'documents' => $documentVariables,
    'all_variables' => $allVariables,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n✅ Analyse sauvegardée dans: document-variables-analysis.json\n";
