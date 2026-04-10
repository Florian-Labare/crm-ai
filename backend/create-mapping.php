<?php

/**
 * Créer le mapping intelligent entre les variables des documents et les champs BDD
 */

require __DIR__.'/vendor/autoload.php';

// Charger l'analyse des documents
$analysisFile = __DIR__.'/document-variables-analysis.json';
if (! file_exists($analysisFile)) {
    exit("❌ Fichier d'analyse non trouvé. Exécutez d'abord analyze-all-templates.php\n");
}

$analysis = json_decode(file_get_contents($analysisFile), true);
$allVariables = $analysis['all_variables'];

// Charger le mapping existant
$existingMapping = include __DIR__.'/config/document_mapping.php';

echo '🔍 Analyse de '.count($allVariables)." variables\n";
echo '📊 Mapping existant: '.count($existingMapping)." variables\n\n";

// Fonction pour normaliser une chaîne (supprimer accents, espaces, minuscules)
function normalize($str)
{
    $str = mb_strtolower($str);
    $str = str_replace([' ', '-', '_', 'é', 'è', 'ê', 'à', 'â', 'ô', 'î', 'ï', 'ù', 'û', 'ç'],
        ['', '', '', 'e', 'e', 'e', 'a', 'a', 'o', 'i', 'i', 'u', 'u', 'c'], $str);

    return $str;
}

// Créer un mapping automatique basé sur la similarité
$autoMapping = [];
$mappedCount = 0;
$unmappedVariables = [];

foreach ($allVariables as $docVar) {
    $normalizedDocVar = normalize($docVar);

    // Chercher dans le mapping existant
    $found = false;

    // 1. Correspondance exacte (insensible à la casse)
    foreach ($existingMapping as $mappedVar => $config) {
        if (normalize($mappedVar) === $normalizedDocVar) {
            $autoMapping[$docVar] = $mappedVar;
            $mappedCount++;
            $found = true;
            break;
        }
    }

    if (! $found) {
        // 2. Correspondance partielle (similarité)
        $bestMatch = null;
        $bestScore = 0;

        foreach ($existingMapping as $mappedVar => $config) {
            $normalizedMappedVar = normalize($mappedVar);

            // Calculer la similarité
            similar_text($normalizedDocVar, $normalizedMappedVar, $percent);

            if ($percent > $bestScore && $percent > 70) { // Seuil 70%
                $bestScore = $percent;
                $bestMatch = $mappedVar;
            }
        }

        if ($bestMatch) {
            $autoMapping[$docVar] = $bestMatch;
            $mappedCount++;
            $found = true;
        }
    }

    if (! $found) {
        $unmappedVariables[] = $docVar;
    }
}

echo "✅ Variables mappées automatiquement: {$mappedCount}/".count($allVariables)."\n";
echo '⚠️  Variables non mappées: '.count($unmappedVariables)."\n\n";

// Afficher le résultat
echo '='.str_repeat('=', 78)."\n";
echo "MAPPING AUTOMATIQUE\n";
echo '='.str_repeat('=', 78)."\n\n";

foreach ($autoMapping as $docVar => $mappedVar) {
    $config = $existingMapping[$mappedVar];
    $source = $config['source'] ?? 'unknown';
    $field = $config['field'] ?? 'unknown';

    echo str_pad($docVar, 40).' → '.str_pad($mappedVar, 25)." ({$source}.{$field})\n";
}

if (! empty($unmappedVariables)) {
    echo "\n".str_repeat('=', 80)."\n";
    echo "VARIABLES NON MAPPÉES (à traiter manuellement)\n";
    echo str_repeat('=', 80)."\n\n";

    foreach ($unmappedVariables as $var) {
        echo "   ❌ {$var}\n";
    }
}

// Sauvegarder le mapping
$outputFile = __DIR__.'/document-variables-mapping.json';
file_put_contents($outputFile, json_encode([
    'mapped' => $autoMapping,
    'unmapped' => $unmappedVariables,
    'stats' => [
        'total' => count($allVariables),
        'mapped' => $mappedCount,
        'unmapped' => count($unmappedVariables),
        'coverage' => round(($mappedCount / count($allVariables)) * 100, 2),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n✅ Mapping sauvegardé dans: document-variables-mapping.json\n";
echo '📊 Couverture: '.round(($mappedCount / count($allVariables)) * 100, 2)."%\n";
